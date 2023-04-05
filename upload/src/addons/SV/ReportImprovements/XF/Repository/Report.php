<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\ReportImprovements\XF\Repository;

use SV\ReportImprovements\Enums\ReportType;
use SV\ReportImprovements\Report\ReportSearchFormInterface;
use SV\ReportImprovements\Repository\ReportQueue as ReportQueueRepo;
use SV\ReportImprovements\XF\Entity\ReportComment;
use SV\ReportImprovements\Entity\WarningLog;
use SV\ReportImprovements\XF\Entity\User as ExtendedUserEntity;
use SV\WarningImprovements\Entity\WarningCategory;
use XF\Db\Exception as DbException;
use SV\ReportImprovements\XF\Entity\Report as ReportEntity;
use XF\Entity\Moderator as ModeratorEntity;
use XF\Entity\User as UserEntity;
use XF\Entity\WarningDefinition;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Entity;
use XF\Phrase;
use XF\Report\AbstractHandler;
use XF\Repository\Moderator;
use XF\Search\MetadataStructure;
use function array_keys;
use function assert;
use function count;
use function get_class;
use function sort;
use function strcmp;
use function trigger_error;

/**
 * Class Report
 * Extends \XF\Repository\Report
 *
 * @package SV\ReportImprovements\XF\Repository
 */
class Report extends XFCP_Report
{
    public function getReportSearchMetaData(ReportEntity $report): array
    {
        $metaData = [
            'report'              => $report->report_id,
            'report_state'        => $report->report_state,
            'report_content_type' => $report->content_type,
            'report_type'         => ReportType::Reported_content,
            'content_user'        => $report->content_user_id, // duplicate of report.user_id
        ];

        if ($report->assigner_user_id)
        {
            $metaData['assigner_user'] = $report->assigner_user_id;
        }

        if ($report->assigned_user_id)
        {
            $metaData['assigned_user'] = $report->assigned_user_id;
        }

        $reportHandler = $this->getReportHandler($report->content_type, null);
        if ($reportHandler instanceof ReportSearchFormInterface)
        {
            $reportHandler->populateMetaData($report, $metaData);
        }

        return $metaData;
    }

    public function setupMetadataStructureForReport(MetadataStructure $structure): void
    {
        foreach ($this->getReportHandlers() as $handler)
        {
            if ($handler instanceof ReportSearchFormInterface)
            {
                $handler->setupMetadataStructure($structure);
            }
        }
        $structure->addField('report_type', MetadataStructure::KEYWORD);
        $structure->addField('report', MetadataStructure::INT);
        $structure->addField('report_state', MetadataStructure::KEYWORD);
        $structure->addField('report_content_type', MetadataStructure::KEYWORD);
        $structure->addField('content_user', MetadataStructure::INT);
        $structure->addField('assigned_user', MetadataStructure::INT);
        $structure->addField('assigner_user', MetadataStructure::INT);
    }

    public function svPreloadReportComments(AbstractCollection $entities)
    {
        $reports = [];
        foreach ($entities as $entity)
        {
            if ($entity instanceof WarningLog)
            {
                $entity = $entity->ReportComment;
            }
            if ($entity instanceof ReportComment)
            {
                $reports[$entity->report_id] = $entity->Report;
            }
        }

        $this->svPreloadReports(new ArrayCollection($reports));
    }

    public function svPreloadReports(AbstractCollection $reports)
    {
        $reportsByContentType = [];
        /** @var ReportEntity $report */
        foreach ($reports as $report)
        {
            if (!$report)
            {
                continue;
            }
            $contentType = $report->content_type;
            $handler = $this->getReportHandler($contentType, false);
            if (!$handler)
            {
                continue;
            }

            $reportsByContentType[$contentType][$report->content_id] = $report;

            // preload title, this only triggers phrase loading if touched in a stringy context
            $report->title;
        }

        foreach ($reportsByContentType as $contentType => $reports)
        {
            $handler = $this->getReportHandler($contentType, false);
            if (!$handler)
            {
                continue;
            }
            $contentIds = array_keys($reports);
            if (!$contentIds)
            {
                continue;
            }
            $reportContents = $handler->getContent($contentIds);
            foreach ($reportContents as $contentId => $reportContent)
            {
                /** @var ReportEntity $report */
                $report = $reports[$contentId] ?? null;
                if (!$report)
                {
                    continue;
                }
                if ($reportContent)
                {
                    $report->setContent($reportContent);
                }
            }
        }
    }

    /**
     * @param array    $state
     * @param int|null $timeFrame
     * @return \XF\Finder\Report
     */
    public function findReports($state = ['open', 'assigned'], $timeFrame = null)
    {
        $finder = parent::findReports($state, $timeFrame);

        $finder->with(['LastModified', 'LastModifiedUser']);

        return $finder;
    }

    /**
     * @param \XF\Entity\Report $report
     * @param bool         $doCache
     * @return int[]
     * @throws DbException
     * @noinspection PhpDocMissingThrowsInspection
     * @noinspection SqlResolve
     */
    public function svGetUsersWhoCanHandleReport(\XF\Entity\Report $report, bool $doCache = true): ?array
    {
        $reportQueueRepo = $this->repository('SV\ReportImprovements:ReportQueue');
        assert($reportQueueRepo instanceof ReportQueueRepo);
        $reportQueueId = (int)($report->queue_id ?? 0);
        $key = $reportQueueRepo->getReportAssignableNonModeratorsCacheKey($reportQueueId);
        $cacheTime = $reportQueueRepo->getReportAssignableNonModeratorsCacheTime($reportQueueId);
        $cache = \XF::app()->cache();

        $userIds = null;
        if ($doCache && $cache && $key && $cacheTime)
        {
            $userIds = @$cache->fetch($key);
            $userIds = \is_array($userIds) ? $userIds : null;
        }

        // apply sanity check limit <= 0 means no limit. WHY
        $options = \XF::options();
        $limit = (int)($options->svReportHandlingLimit ?? 1000);
        $limit = max(0, $limit);

        if (!\is_array($userIds))
        {
            // sanity check permissions have the expected range of values
            foreach ([
                         'XF:PermissionEntry' => ['unset', 'allow', 'deny', 'use_int'],
                         'XF:PermissionEntryContent' => ['unset', 'reset', 'content_allow', 'deny', 'use_int'],
                     ] as $entityName => $expected)
            {
                $structure = $this->app()->em()->getEntityStructure($entityName);
                $allowedValues = array_values(($structure->columns['permission_value']['allowedValues'] ?? []));
                sort($allowedValues);
                sort($expected);
                if ($allowedValues !== $expected)
                {
                    $error = "Unexpected {$entityName} configuration, expected ";
                    if (\XF::$debugMode)
                    {
                        trigger_error($error, E_USER_WARNING);
                    }
                    \XF::logError($error);

                    return [];
                }
            }

            // find users with groups with the update report, or via direct permission assignment but aren't moderators
            // ensure they can view the report centre, or this might return more users than expected
            // this requires an index on xf_permission_entry.permission_group_id/xf_permission_entry.permission_id to be effective
            // this can still be slow-ish initially, so cache.
            // Note; to avoid catastrophically poor performance in older versions of MySQL, do this incrementally via explicit temp tables
            $db = \XF::db();
            $db->query('DROP TEMPORARY TABLE IF EXISTS xf_sv_non_moderator_report_users');

            // Report queues are "flat" and can not be nested within each other. As such do not need to worry about how unbound permission overriding works
            // canView/canUpdate values (in order of priority);
            // 0    - denied
            // 1    - allowed
            // null - not yet denied
            $db->query('
            CREATE TEMPORARY TABLE xf_sv_non_moderator_report_users (
                user_id int UNSIGNED NOT NULL PRIMARY KEY,
                canView tinyint(10) DEFAULT NULL,
                canUpdate tinyint(10) DEFAULT NULL
            )');
            // build the initial list of users who can view the report centre
            $db->query("
                INSERT INTO xf_sv_non_moderator_report_users (user_id, canView)
                SELECT a.user_id, if(a.permission_value = 'allow', 1, 0) as val
                FROM (
                    SELECT DISTINCT gr.user_id, groupPerm.permission_value 
                    FROM xf_permission_entry AS groupPerm use index (permission_group_id_permission_id)
                    STRAIGHT_JOIN xf_user_group_relation AS gr use index (user_group_id_is_primary) ON groupPerm.user_group_id = gr.user_group_id
                    WHERE 
                         groupPerm.permission_group_id = 'general' AND groupPerm.permission_id = 'viewReports'
                ) a
                ON DUPLICATE KEY UPDATE
                    canView = if(canView = 0 OR a.permission_value = 'never' OR a.permission_value = 'reset', 0, if(a.permission_value = 'allow', 1, NULL))
            ");
            $db->query("
                INSERT INTO xf_sv_non_moderator_report_users (user_id, canView)
                SELECT DISTINCT userPerm.user_id, if(permission_value = 'allow', 1, 0) as val
                FROM xf_permission_entry AS userPerm use index (permission_group_id_permission_id)
                WHERE userPerm.permission_group_id = 'general' AND userPerm.permission_id = 'viewReports'
                ON DUPLICATE KEY UPDATE
                    canView = if(canView = 0 OR userPerm.permission_value = 'never' OR userPerm.permission_value = 'reset', 0, if(userPerm.permission_value = 'allow', 1, NULL))
            ");
            // prune the set. phpstorm gets a left-join is null wrong
            /** @noinspection SqlConstantExpression */
            $db->query("
                DELETE reportUsers
                FROM xf_sv_non_moderator_report_users AS reportUsers
                LEFT JOIN xf_user AS xu ON reportUsers.user_id = xu.user_id
                WHERE reportUsers.canView = 0 or reportUsers.canView is null or reportUsers.user_id = 0 or xu.user_id is null or xu.user_state <> 'valid'
            ");

            $tablesToCheck = [
                'xf_permission_entry' => '',
            ];
            if ($reportQueueId !== 0)
            {
                $tablesToCheck['xf_permission_entry_content'] = 'content_type = \'report_queue\' AND content_id = '.$db->quote($reportQueueId). ' AND ';

                // merge in the list of users who can view reports in a given report queue, checking the global report_queue.view permission as well
                foreach ($tablesToCheck as $table => $contentFilterSql)
                {
                    $db->query("
                        UPDATE xf_sv_non_moderator_report_users as reportUsers
                        JOIN xf_user_group_relation AS gr ON reportUsers.user_id = gr.user_id
                        JOIN $table AS groupPerm On groupPerm.user_group_id = gr.user_group_id                
                        set reportUsers.canView = if(canView = 0 OR groupPerm.permission_value = 'deny' OR groupPerm.permission_value = 'reset', 0, if(groupPerm.permission_value = 'allow' or groupPerm.permission_value = 'content_allow', 1, NULL))
                        WHERE $contentFilterSql groupPerm.permission_group_id = 'report_queue' AND groupPerm.permission_id = 'view'
                    ");
                    $db->query("
                        UPDATE xf_sv_non_moderator_report_users as reportUsers
                        JOIN $table AS userPerm On reportUsers.user_id = userPerm.user_id                
                        set reportUsers.canView = if(canView = 0 OR userPerm.permission_value = 'deny' OR userPerm.permission_value = 'reset', 0, if(userPerm.permission_value = 'allow' or userPerm.permission_value = 'content_allow', 1, NULL))
                        WHERE $contentFilterSql userPerm.permission_group_id = 'report_queue' AND userPerm.permission_id = 'view'
                    ");
                }

                $db->query('
                    DELETE reportUsers
                    FROM xf_sv_non_moderator_report_users AS reportUsers
                    WHERE canView = 0 or canView is null
                ');
            }
            // merge in the list of users who can update reports
            foreach ($tablesToCheck as $table => $contentFilterSql)
            {
                $db->query("
                    UPDATE xf_sv_non_moderator_report_users as reportUsers
                    JOIN xf_user_group_relation AS gr ON reportUsers.user_id = gr.user_id
                    JOIN $table AS groupPerm On groupPerm.user_group_id = gr.user_group_id                
                    set reportUsers.canUpdate = if(canUpdate = 0 OR groupPerm.permission_value = 'deny' OR groupPerm.permission_value = 'reset', 0, if(groupPerm.permission_value = 'allow' or groupPerm.permission_value = 'content_allow', 1, NULL))
                    WHERE $contentFilterSql groupPerm.permission_group_id = 'report_queue' AND groupPerm.permission_id = 'updateReport'
                ");
                $db->query("
                    UPDATE xf_sv_non_moderator_report_users as reportUsers
                    JOIN $table AS userPerm On reportUsers.user_id = userPerm.user_id                
                    set reportUsers.canUpdate = if(canUpdate = 0 OR userPerm.permission_value = 'deny' OR userPerm.permission_value = 'reset', 0, if(userPerm.permission_value = 'allow' or userPerm.permission_value = 'content_allow', 1, NULL))
                    WHERE $contentFilterSql userPerm.permission_group_id = 'report_queue' AND userPerm.permission_id = 'updateReport'
                ");
            }

            $userIds = $db->fetchAllColumn('SELECT user_id FROM xf_sv_non_moderator_report_users where canUpdate = 1 and user_id <> 0');

            if ($cache && $key && $cacheTime)
            {
                $cache->save($key, $userIds, $cacheTime);
            }
        }

        $count = count($userIds);
        if ($limit && $count > $limit)
        {
            $error = "Potential miss-configuration detected. {$count} users have access to handle/update this report via permissions. Sanity limit is {$limit}, to adjust edit the 'Maximum non-moderator users who can handle reports' option";
            if (\XF::$debugMode)
            {
                trigger_error($error, E_USER_WARNING);
            }
            \XF::logError($error);
            return [];
        }

        return $userIds;
    }

    /**
     * @param \XF\Entity\Report $report
     * @return ArrayCollection
     * @throws \Exception
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function getModeratorsWhoCanHandleReport(\XF\Entity\Report $report)
    {
        /** @var ReportEntity $report */
        $userIds = $this->svGetUsersWhoCanHandleReport($report);
        if (count($userIds) === 0)
        {
            // no users who can have reports assigned to them. permissions need fixing
            if (\XF::$debugMode)
            {
                trigger_error('No users have the "Update Report" permission', E_USER_WARNING);
            }

            return new ArrayCollection([]);
        }

        // load into memory, but this is non-authoritative
        /** @var Moderator $moderatorRepo */
        $moderatorRepo = $this->repository('XF:Moderator');
        $moderatorRepo->findModeratorsForList()
                      ->fetch();

        $permCombinationIds = [];
        $usersToFetch = [];
        /** @var array<int,ModeratorEntity> $moderators */
        $moderators = [];

        $fakeMod = function(int $userId): ModeratorEntity {
            /** @var ModeratorEntity $moderator */
            $moderator = $this->em->create('XF:Moderator');
            $moderator->setTrusted('user_id', $userId);
            $moderator->hydrateRelation('User', $this->em->find('XF:User', $userId));
            $moderator->setReadOnly(true);
            return $moderator;
        };

        foreach ($userIds as $userId)
        {
            $user = $this->em->findCached('XF:User', $userId);
            if ($user instanceof UserEntity)
            {
                $id = $user->permission_combination_id;
                $permCombinationIds[$id] = $id;

                $moderator = $this->em->findCached('XF:Moderator', $userId);
                $moderators[$userId] = $moderator instanceof ModeratorEntity
                    ? $moderator
                    : $fakeMod($userId);
            }
            else
            {
                $usersToFetch[$userId] = $userId;
            }
        }

        if (count($usersToFetch) !== 0)
        {
            $users = \XF::finder('XF:User')
                        ->where('user_id', '=', $usersToFetch)
                        ->order('user_id')
                        ->fetch();
            foreach ($users as $userId => $user)
            {
                assert($user instanceof UserEntity);
                $id = $user->permission_combination_id;
                $permCombinationIds[$id] = $id;

                $moderators[$userId] = $fakeMod($userId);
            }
        }

        if (count($permCombinationIds) !== 0)
        {
            $permCombinationIds = array_keys($permCombinationIds);
            $nodeId = (int)($report->content_info['node_id'] ?? 0);
            if ($nodeId !== 0)
            {
                $this->em->find('XF:Forum', $nodeId);
            }
            if ($nodeId !== 0)
            {
                $this->app()->permissionCache()->cacheMultipleContentPermsForContent($permCombinationIds, 'node', $nodeId);
            }
            $reportQueueId = (int)($report->queue_id ?? 0);
            if ($reportQueueId !== 0)
            {
                $this->app()->permissionCache()->cacheMultipleContentPermsForContent($permCombinationIds, 'report_queue', $reportQueueId);
            }
        }

        $canViewFunc = function () use ($report) {
            return $report->canView() && $report->canUpdate();
        };
        foreach ($moderators AS $id => $moderator)
        {
            if (!\XF::asVisitor($moderator->User, $canViewFunc))
            {
                unset($moderators[$id]);
            }
        }

        usort($moderators, function (ModeratorEntity $a, ModeratorEntity $b): int {
            return strcmp($a->User->username, $b->User->username);
        });

        return new ArrayCollection($moderators);
    }

    /**
     * @param AbstractCollection $reports
     * @return AbstractCollection
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function filterViewableReports($reports)
    {
        $em = $this->app()->em();

        // avoid N+1 look up behaviour, just cache all node perms
        \XF::visitor()->cacheNodePermissions();

        // pre-XF2.0.12 and a few versions of XF2.1 have a bug where they skip Report::canView check, and XF2.1 marks it as deprecated
        $reports = $reports->filterViewable();

        $userIds = [];
        /** @var ReportEntity $report */
        foreach ($reports as $report)
        {
            $report->title;
            $userIds[$report->content_user_id] = true;
            $userIds[$report->assigned_user_id] = true;
            $userIds[$report->last_modified_user_id] = true;
        }

        foreach ($userIds as $userId => $null)
        {
            if (!$userId || $em->findCached('XF:User', $userId))
            {
                unset($userIds[$userId]);
            }
        }

        if ($userIds)
        {
            $em->findByIds('XF:User', array_keys($userIds));
        }

        return $reports;
    }

    /**
     * @param Report|ReportComment $entity
     * @return int[]
     * @noinspection PhpDocMissingThrowsInspection
     */
    public function findUserIdsToAlertForSvReportImprov(Entity $entity): array
    {
        $userIds = [];
        $alertMode = $this->options()->sv_report_alert_mode ?? 'none';
        if ($alertMode === 'none')
        {
            return $userIds;
        }

        if ($entity instanceof \XF\Entity\Report)
        {
            if ($alertMode !== 'watchers')
            {
                $moderators = $this->getModeratorsWhoCanHandleReport($entity);
                if ($moderators->count())
                {
                    /** @var ModeratorEntity $moderator */
                    foreach ($moderators AS $moderator)
                    {
                        $userIds[] = $moderator->user_id;
                    }
                }
            }
        }
        else if ($entity instanceof \XF\Entity\ReportComment)
        {
            if ($alertMode !== 'always_alert')
            {
                $db = $this->db();
                $userIds = $db->fetchAllColumn('
                    SELECT DISTINCT user_id
                    FROM xf_report_comment
                    WHERE report_id = ?
                      AND user_id <> ?
                ', [$entity->report_id, $entity->user_id]);
            }

            /** @var ReportEntity $report */
            $report = $entity->Report;
            if ($entity->state_change === 'assigned' && $report->assigned_user_id)
            {
                // alerts the assigned user who likely isn't a watcher
                $userIds[] = $report->assigned_user_id;
                $userIds = \array_unique($userIds);
            }
        }
        else
        {
            throw new \InvalidArgumentException(__METHOD__.'($entity) is not a report or report comment, and is intead type:' . get_class($entity));
        }

        return $userIds ?: [];
    }

    protected $userReportCountCache = null;

    /**
     * @param UserEntity|ExtendedUserEntity $user
     * @param int             $daysLimit
     * @param string          $state
     * @return int
     */
    public function countReportsByUser(UserEntity $user, int $daysLimit, string $state = ''): int
    {
        if ($this->userReportCountCache === null)
        {
            $this->userReportCountCache = [];
        }

        if (!isset($this->userReportCountCache[$user->user_id][$daysLimit][$state]))
        {
            $db = $this->db();

            $params = [$user->user_id];
            $additionalWhere = '';
            if ($daysLimit)
            {
                $params[] = \XF::$time - (86400 * $daysLimit);
                $additionalWhere = 'AND report_comment.comment_date >= ?';
            }

            $reportStats = $db->fetchAll(
                "SELECT report.report_state, COUNT(*) AS total
                FROM xf_report_comment AS report_comment
                INNER JOIN xf_report AS report
                  ON (report.report_id = report_comment.report_id)
                WHERE report_comment.is_report = 1
                  AND report_comment.user_id = ?
                  {$additionalWhere}"
                , $params);

            $stats = [];
            $total = 0;
            foreach ($reportStats AS $reportStat)
            {
                $total += $reportStat['total'];
                $stats[$reportStat['report_state']] = $reportStat['total'];
            }
            $stats[''] = $total;
            $this->userReportCountCache[$user->user_id][$daysLimit] = $stats;
        }

        if (isset($this->userReportCountCache[$user->user_id][$daysLimit][$state]))
        {
            return $this->userReportCountCache[$user->user_id][$daysLimit][$state];
        }

        return 0;
    }

    /**
     * @param array $registryReportCounts
     * @return array
     */
    public function rebuildSessionReportCounts(array $registryReportCounts)
    {
        /** @var \XF\Finder\Report $reportFinder */
        $reportFinder = $this->app()->finder('XF:Report');
        $reports = $reportFinder->isActive()->fetch();
        $reports = $this->filterViewableReports($reports);

        $total = 0;
        $assigned = 0;
        $userId = \XF::visitor()->user_id;

        /**
         * @var int          $reportId
         * @var ReportEntity $report
         */
        foreach ($reports AS $report)
        {
            $total++;
            if ($report->assigned_user_id === $userId)
            {
                $assigned++;
            }
        }

        return [
            'total'     => $total,
            'assigned'  => $assigned,
            'lastBuilt' => $registryReportCounts['lastModified'],
        ];
    }

    /**
     * @return array<string>
     */
    public function getReportStates(): array
    {
        $structure = \XF::app()->em()->getEntityStructure('XF:Report');
        // This list is extended by other add-ons
        $states = $structure->columns['report_state']['allowedValues'] ?? [];
        assert(is_array($states) && count($states) > 0);

        return $states;
    }

    /**
     * @return array<string,Phrase>
     */
    public function getReportStatePairs(): array
    {
        $states = [];
        foreach ($this->getReportStates() as $state)
        {
            $states[$state] = \XF::phrase('report_state.' . $state);
        }

        return $states;
    }

    /**
     * This function exists to allow templates to access it via $xf.app.em.getRepository('XF:Report').getReportTypePairs()
     *
     * @return array<string,Phrase>
     */
    public function getReportTypePairs(): array
    {
        return ReportType::getPairs();
    }

    /**
     * @param bool $plural
     * @return array<string,Phrase>
     */
    public function getReportContentTypePhrasePairs(bool $plural): array
    {
        $contentTypes = [];
        $app = $this->app();

        foreach ($this->getReportHandlers() as $contentType => $handler)
        {
            $contentTypes[$contentType] = $app->getContentTypePhrase($contentType, $plural);
        }

        return $contentTypes;
    }

    /**
     * @return array<string, AbstractHandler>
     */
    public function getReportHandlers(): array
    {
        $contentTypes = [];
        $app = $this->app();

        foreach ($app->getContentTypeField('report_handler_class') as $contentType => $className)
        {
            $handler = $this->getReportHandler($contentType, false);
            if ($handler === null)
            {
                continue;
            }
            $contentTypes[$contentType] = $handler;
        }

        return $contentTypes;
    }

    /**
     * @return array<string,Phrase>
     */
    public function getWarningDefinitionsForSearch(): array
    {
        $phrasePairs = [];

        $warningRepo = $this->repository('XF:Warning');
        assert($warningRepo instanceof \XF\Repository\Warning);
        if (\XF::isAddOnActive('SV/WarningImprovements'))
        {
            assert($warningRepo instanceof \SV\WarningImprovements\XF\Repository\Warning);

            $categoryRepo = $this->repository('SV\WarningImprovements:WarningCategory');
            assert($categoryRepo instanceof \SV\WarningImprovements\Repository\WarningCategory);

            $categories = $categoryRepo->findCategoryList()->fetch();
            $categoryTree = $categoryRepo->createCategoryTree($categories);
            $warningsByCategory = $warningRepo->findWarningDefinitionsForListGroupedByCategory();

            foreach ($categoryTree->getFlattened(0) as $treeNode)
            {
                $category = $treeNode['record'];
                assert($category instanceof WarningCategory);
                $warningDefinitions = $warningsByCategory[$category->category_id] ?? [];
                foreach ($warningDefinitions as $id => $warningDefinition)
                {
                    assert($warningDefinition instanceof \SV\WarningImprovements\XF\Entity\WarningDefinition);
                    if ($warningDefinition->isUsable())
                    {
                        $phrasePairs[$id] = $warningDefinition->title;
                    }
                }
            }
        }
        else
        {
            $definitions = $warningRepo->findWarningDefinitionsForList()->fetch();

            $phrasePairs = [
                0 => \XF::phrase('custom_warning'),
            ];
            foreach ($definitions as $id => $warningDefinition)
            {
                assert($warningDefinition instanceof WarningDefinition);
                $phrasePairs[$id] = $warningDefinition->title;
            }
        }

        return $phrasePairs;
    }

}
