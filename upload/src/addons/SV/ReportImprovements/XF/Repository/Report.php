<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\ReportImprovements\XF\Repository;

use LogicException;
use SV\ReportImprovements\Enums\ReportType;
use SV\ReportImprovements\Report\ReportSearchFormInterface;
use SV\ReportImprovements\Repository\ReportQueue as ReportQueueRepo;
use SV\ReportImprovements\XF\Entity\ReportComment as ExtendedReportCommentEntity;
use SV\ReportImprovements\Entity\WarningLog as WarningLogEntity;
use SV\ReportImprovements\XF\Entity\User as ExtendedUserEntity;
use SV\StandardLib\Helper;
use SV\WarningImprovements\Entity\WarningCategory as WarningCategoryEntity;
use SV\WarningImprovements\Repository\WarningCategory as WarningCategoryRepo;
use SV\WarningImprovements\XF\Entity\WarningDefinition as ExtendedWarningDefinitionEntity;
use SV\WarningImprovements\XF\Repository\Warning as ExtendedWarningRepo;
use XF\Db\Exception as DbException;
use SV\ReportImprovements\XF\Entity\Report as ExtendedReportEntity;
use XF\Entity\Forum as ForumEntity;
use XF\Entity\Moderator as ModeratorEntity;
use XF\Entity\Report as ReportEntity;
use XF\Entity\ReportComment as ReportCommentEntity;
use XF\Entity\User as UserEntity;
use XF\Entity\WarningDefinition as WarningDefinitionEntity;
use XF\Finder\Report as ReportFinder;
use XF\Finder\User as UserFinder;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Entity;
use XF\Phrase;
use XF\Report\AbstractHandler;
use XF\Repository\Moderator as ModeratorRepo;
use XF\Repository\Warning as WarningRepo;
use XF\Search\IndexRecord;
use XF\Search\MetadataStructure;
use function array_keys;
use function array_unique;
use function array_values;
use function assert;
use function count;
use function function_exists;
use function get_class;
use function in_array;
use function is_array;
use function max;
use function sort;
use function strlen;
use function substr_compare;
use function trigger_error;

if (!function_exists('str_ends_with'))
{
    function str_ends_with(string $haystack, string $needle): bool
    {
        if ('' === $needle || $needle === $haystack)
        {
            return true;
        }

        if ('' === $haystack)
        {
            return false;
        }

        $needleLength = strlen($needle);

        return $needleLength <= strlen($haystack) && 0 === substr_compare($haystack, $needle, -$needleLength);
    }
}

/**
 * @extends \XF\Repository\Report
 */
class Report extends XFCP_Report
{
    public function hasContentVisibilityChanged(Entity $entity): bool
    {
        foreach ($entity->structure()->columns as $column => $def)
        {
            if (($def['type'] ?? '') !== Entity::STR)
            {
                continue;
            }

            // todo support php enums
            $allowedValues = $def['allowedValues'] ?? null;
            if (!is_array($allowedValues) || !in_array('visible', $allowedValues, true))
            {
                continue;
            }

            if (!str_ends_with($column, '_state'))
            {
                continue;
            }

            if ($entity->isChanged($column) && ($entity->getPreviousValue($column) === 'visible' || $entity->get($column) === 'visible'))
            {
                return true;
            }
        }

        return false;
    }

    public function getReportSearchMetaData(ExtendedReportEntity $report): array
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

        $content = $report->Content;
        if ($content !== null)
        {
            if ($this->isContentWarned($content))
            {
                $metaData['content_warned'] = true;
            }
            $contentIndexRecord = $this->getContentIndexRecord($report);
            if ($contentIndexRecord === null || $contentIndexRecord->hidden)
            {
                $metaData['content_deleted'] = true;
            }
        }

        return $metaData;
    }

    protected function getContentIndexRecord(ExtendedReportEntity $report): ?IndexRecord
    {
        /** @var Entity|null $content */
        $content = $report->Content;
        if ($content === null)
        {
            return null;
        }

        $searcher = \XF::app()->search();
        /** @var string|null $contentType */
        $contentType = $content->getEntityContentType();
        if ($contentType === null  || !$searcher->isValidContentType($contentType))
        {
            return null;
        }
        $searchHandler = $searcher->handler($contentType);

        return $searchHandler->getIndexData($content);
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
        $structure->addField('content_warned', MetadataStructure::BOOL);
        $structure->addField('content_deleted', MetadataStructure::BOOL);
    }

    protected function isContentWarned(Entity $content): bool
    {
        $structure = $content->structure();
        $contentType = $structure->contentType ?? '';
        if ($contentType === '')
        {
            return false;
        }
        $key = $structure->primaryKey;
        if (is_array($key))
        {
            return false;
        }
        $contentId = $content->get($key);

        return (bool)\XF::db()->fetchOne('
            select warning_id 
            from xf_warning 
            where content_type = ? and content_id = ?
            ',[
            $contentType,
            $contentId
        ]);
    }

    public function svPreloadReportComments(AbstractCollection $entities)
    {
        $reports = [];
        foreach ($entities as $entity)
        {
            if ($entity instanceof WarningLogEntity)
            {
                $entity = $entity->ReportComment;
            }
            if ($entity instanceof ExtendedReportCommentEntity)
            {
                $reports[$entity->report_id] = $entity->Report;
            }
        }

        $this->svPreloadReports(new ArrayCollection($reports));
    }

    public function svPreloadReports(AbstractCollection $reports)
    {
        $reportsByContentType = [];
        /** @var ExtendedReportEntity $report */
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
                /** @var ExtendedReportEntity $report */
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
     * @return ReportFinder
     */
    public function findReports($state = ['open', 'assigned'], $timeFrame = null)
    {
        $finder = parent::findReports($state, $timeFrame);

        $finder->with(['LastModified', 'LastModifiedUser']);

        return $finder;
    }

    /**
     * @param ReportEntity $report
     * @param bool         $doCache
     * @return int[]
     * @throws DbException
     * @noinspection PhpDocMissingThrowsInspection
     * @noinspection SqlResolve
     */
    public function svGetUsersWhoCanHandleReport(ReportEntity $report, bool $doCache = true): array
    {
        $reportQueueRepo = Helper::repository(ReportQueueRepo::class);
        $reportQueueId = (int)($report->queue_id ?? 0);
        $key = $reportQueueRepo->getReportAssignableNonModeratorsCacheKey($reportQueueId);
        $cacheTime = $reportQueueRepo->getReportAssignableNonModeratorsCacheTime($reportQueueId);
        $cache = \XF::app()->cache();

        $userIds = null;
        if ($doCache && $cache && $key && $cacheTime)
        {
            $userIds = @$cache->fetch($key);
            $userIds = is_array($userIds) ? $userIds : null;
        }

        // apply sanity check limit <= 0 means no limit. WHY
        $options = \XF::options();
        $limit = $options->svReportHandlingLimit ?? 100;
        $limit = max(0, $limit);

        if (!is_array($userIds))
        {
            // sanity check permissions have the expected range of values
            foreach ([
                         'XF:PermissionEntry' => ['unset', 'allow', 'deny', 'use_int'],
                         'XF:PermissionEntryContent' => ['unset', 'reset', 'content_allow', 'deny', 'use_int'],
                     ] as $entityName => $expected)
            {
                $structure = Helper::getEntityStructure($entityName);
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
                         groupPerm.user_group_id <> 0 AND groupPerm.permission_group_id = 'general' AND groupPerm.permission_id = 'viewReports'
                ) a
                ON DUPLICATE KEY UPDATE
                    canView = if(canView = 0 OR a.permission_value = 'never' OR a.permission_value = 'reset', 0, if(a.permission_value = 'allow', 1, NULL))
            ");
            $db->query("
                INSERT INTO xf_sv_non_moderator_report_users (user_id, canView)
                SELECT DISTINCT userPerm.user_id, if(permission_value = 'allow', 1, 0) as val
                FROM xf_permission_entry AS userPerm use index (permission_group_id_permission_id)
                WHERE userPerm.user_id <> 0 AND userPerm.permission_group_id = 'general' AND userPerm.permission_id = 'viewReports'
                ON DUPLICATE KEY UPDATE
                    canView = if(canView = 0 OR userPerm.permission_value = 'never' OR userPerm.permission_value = 'reset', 0, if(userPerm.permission_value = 'allow', 1, NULL))
            ");
            // prune the set of users who can't view the report center at all
            $db->query('
                DELETE reportUsers
                FROM xf_sv_non_moderator_report_users AS reportUsers
                WHERE user_id = 0 or canView = 0 or canView is null
            ');
            // prune users who aren't in a valid state. phpstorm gets a left-join is null wrong
            /** @noinspection SqlConstantExpression */
            $db->query("
                DELETE reportUsers
                FROM xf_sv_non_moderator_report_users AS reportUsers
                LEFT JOIN xf_user AS xu ON reportUsers.user_id = xu.user_id
                WHERE xu.user_id is null or xu.user_state <> 'valid'
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
                        WHERE $contentFilterSql groupPerm.user_group_id <> 0 AND groupPerm.permission_group_id = 'report_queue' AND groupPerm.permission_id = 'view'
                    ");
                    $db->query("
                        UPDATE xf_sv_non_moderator_report_users as reportUsers
                        JOIN $table AS userPerm On reportUsers.user_id = userPerm.user_id                
                        set reportUsers.canView = if(canView = 0 OR userPerm.permission_value = 'deny' OR userPerm.permission_value = 'reset', 0, if(userPerm.permission_value = 'allow' or userPerm.permission_value = 'content_allow', 1, NULL))
                        WHERE $contentFilterSql userPerm.user_id <> 0 AND userPerm.permission_group_id = 'report_queue' AND userPerm.permission_id = 'view'
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
                    WHERE $contentFilterSql groupPerm.user_group_id <> 0 AND groupPerm.permission_group_id = 'report_queue' AND groupPerm.permission_id = 'updateReport'
                ");
                $db->query("
                    UPDATE xf_sv_non_moderator_report_users as reportUsers
                    JOIN $table AS userPerm On reportUsers.user_id = userPerm.user_id                
                    set reportUsers.canUpdate = if(canUpdate = 0 OR userPerm.permission_value = 'deny' OR userPerm.permission_value = 'reset', 0, if(userPerm.permission_value = 'allow' or userPerm.permission_value = 'content_allow', 1, NULL))
                    WHERE $contentFilterSql userPerm.user_id <> 0 AND userPerm.permission_group_id = 'report_queue' AND userPerm.permission_id = 'updateReport'
                ");
            }

            // note; ReplicationAdapter will revert to using the read connection after the previous queries
            $userIds = $db->fetchAllColumn('-- XFDB=fromWrite
                SELECT user_id
                FROM xf_sv_non_moderator_report_users
                where canUpdate = 1 and canView = 1 and user_id <> 0
            ');

            if ($cache && $key && $cacheTime)
            {
                $cache->save($key, $userIds, $cacheTime);
            }
        }

        if (!is_array($userIds))
        {
            $userIds = [];
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
     * @param ReportEntity $report
     * @param bool         $notifiableOnly
     * @return ArrayCollection<ModeratorEntity>
     * @noinspection PhpDocMissingThrowsInspection
     */
    public function svGetModeratorsWhoCanHandleReport(ReportEntity $report, bool $notifiableOnly = false)
    {
        /** @var ExtendedReportEntity $report */
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
        /** @var ModeratorRepo $moderatorRepo */
        $moderatorRepo = Helper::repository(ModeratorRepo::class);
        $moderatorRepo->findModeratorsForList()
                      ->fetch();

        $permCombinationIds = [];
        $usersToFetch = [];
        /** @var array<int,ModeratorEntity> $moderators */
        $moderators = [];

        $fakeMod = function (int $userId, UserEntity $user): ModeratorEntity {
            /** @var ModeratorEntity $moderator */
            $moderator = Helper::createEntity(ModeratorEntity::class);
            $moderator->setTrusted('user_id', $userId);
            if (\XF::$versionId >= 2030000)
            {
                $moderator->setTrusted('notify_report', false);
            }
            $moderator->hydrateRelation('User', $user);
            $moderator->setReadOnly(true);

            return $moderator;
        };

        foreach ($userIds as $userId)
        {
            $user = Helper::findCached(UserEntity::class, $userId);
            if ($user !== null)
            {
                $id = $user->permission_combination_id;
                $permCombinationIds[$id] = $id;

                $moderator = Helper::findCached(ModeratorEntity::class, $userId);
                if ($moderator === null)
                {
                    $moderator = $fakeMod($userId, $user);
                }

                if ($moderator === null || $moderator->User === null || $notifiableOnly && !$moderator->notify_report)
                {
                    continue;
                }

                $moderators[$userId] = $moderator;
            }
            else
            {
                $usersToFetch[$userId] = $userId;
            }
        }

        if (count($usersToFetch) !== 0)
        {
            /** @var array<int,UserEntity> $users */
            $users = Helper::finder(UserFinder::class)
                           ->where('user_id', '=', $usersToFetch)
                           ->order('user_id')
                           ->fetch();
            foreach ($users as $userId => $user)
            {
                $moderator = $fakeMod($userId, $user);
                if ($notifiableOnly && !$moderator->notify_report)
                {
                    continue;
                }

                $id = $user->permission_combination_id;
                $permCombinationIds[$id] = $id;
                $moderators[$userId] = $moderator;
            }
        }

        if (count($permCombinationIds) !== 0)
        {
            $permCombinationIds = array_keys($permCombinationIds);
            $nodeId = (int)($report->content_info['node_id'] ?? 0);
            if ($nodeId !== 0)
            {
                Helper::find(ForumEntity::class, $nodeId);
            }
            if ($nodeId !== 0)
            {
                \XF::app()->permissionCache()->cacheMultipleContentPermsForContent($permCombinationIds, 'node', $nodeId);
            }
            $reportQueueId = (int)($report->queue_id ?? 0);
            if ($reportQueueId !== 0)
            {
                \XF::app()->permissionCache()->cacheMultipleContentPermsForContent($permCombinationIds, 'report_queue', $reportQueueId);
            }
        }

        $canViewFunc = function () use ($report) {
            return $report->canView() && $report->canUpdate();
        };
        foreach ($moderators as $id => $moderator)
        {
            if (!\XF::asVisitor($moderator->User, $canViewFunc))
            {
                unset($moderators[$id]);
            }
        }

        if (count($moderators) === 0)
        {
            return new ArrayCollection([]);
        }

        // sorting string is hard, use mysql to at least be consistent with how XF returns this list
        $db = \XF::db();
        $keys = $db->fetchAllColumn('
            select user_id 
            from xf_user 
            where user_id in (' . $db->quote(array_keys($moderators)) . ')
            order by username
        ');

        return (new ArrayCollection($moderators))->sortByList($keys);
    }

    /**
     * @param AbstractCollection $reports
     * @return AbstractCollection
     * @noinspection PhpMissingParentCallCommonInspection
     * @noinspection PhpMissingParamTypeInspection
     * @noinspection RedundantSuppression
     */
    public function filterViewableReports($reports)
    {
        // avoid N+1 look up behaviour, just cache all node perms
        \XF::visitor()->cacheNodePermissions();

        // pre-XF2.0.12 and a few versions of XF2.1 have a bug where they skip Report::canView check, and XF2.1 marks it as deprecated
        $reports = $reports->filterViewable();

        $userIds = [];
        /** @var ExtendedReportEntity $report */
        foreach ($reports as $report)
        {
            $report->title;
            $userIds[$report->content_user_id] = true;
            $userIds[$report->assigned_user_id] = true;
            $userIds[$report->last_modified_user_id] = true;
        }

        foreach ($userIds as $userId => $null)
        {
            if (!$userId || Helper::findCached(UserEntity::class, $userId))
            {
                unset($userIds[$userId]);
            }
        }

        if ($userIds)
        {
            Helper::findByIds(UserEntity::class, array_keys($userIds));
        }

        return $reports;
    }

    /**
     * @param Report|ExtendedReportCommentEntity $entity
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

        if ($entity instanceof ReportEntity)
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
        else if ($entity instanceof ReportCommentEntity)
        {
            if ($alertMode !== 'always_alert')
            {
                $db = \XF::db();
                $userIds = $db->fetchAllColumn('
                    SELECT DISTINCT user_id
                    FROM xf_report_comment
                    WHERE report_id = ?
                      AND user_id <> ?
                ', [$entity->report_id, $entity->user_id]);
            }

            /** @var ExtendedReportEntity $report */
            $report = $entity->Report;
            if ($entity->state_change === 'assigned' && $report->assigned_user_id)
            {
                // alerts the assigned user who likely isn't a watcher
                $userIds[] = $report->assigned_user_id;
                $userIds = array_unique($userIds);
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
            $db = \XF::db();

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
        $reportFinder = Helper::finder(ReportFinder::class);
        $reports = $reportFinder->isActive()->fetch();
        $reports = $this->filterViewableReports($reports);

        $total = 0;
        $assigned = 0;
        $userId = \XF::visitor()->user_id;

        /**
         * @var int                  $reportId
         * @var ExtendedReportEntity $report
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
        $structure = Helper::getEntityStructure(ReportEntity::class);
        // This list is extended by other add-ons
        $states = $structure->columns['report_state']['allowedValues'] ?? [];
        if (!is_array($states) || count($states) === 0)
        {
            throw new LogicException('Expected allowedValues for Report::report_state to have values');
        }

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

    public function getReportTypeDefaultsForSearch(bool $isAdvancedSearch): array
    {
        // no types selected === all
        if ($isAdvancedSearch)
        {
            return [];
        }

        return ReportType::get();
    }

    /**
     * @param bool $plural
     * @return array<string,Phrase>
     */
    public function getReportContentTypePhrasePairs(bool $plural): array
    {
        $contentTypes = [];
        $app = \XF::app();

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
        $app = \XF::app();

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

        $warningRepo = Helper::repository(WarningRepo::class);
        if (Helper::isAddOnActive('SV/WarningImprovements'))
        {
            /** @var ExtendedWarningRepo $warningRepo */
            $categoryRepo = Helper::repository(WarningCategoryRepo::class);
            $categories = $categoryRepo->findCategoryList()->fetch();
            $categoryTree = $categoryRepo->createCategoryTree($categories);
            $warningsByCategory = $warningRepo->findWarningDefinitionsForListGroupedByCategory();

            foreach ($categoryTree->getFlattened(0) as $treeNode)
            {
                /** @var WarningCategoryEntity $category */
                $category = $treeNode['record'];
                /** @var array<int,ExtendedWarningDefinitionEntity> $warningDefinitions */
                $warningDefinitions = $warningsByCategory[$category->category_id] ?? [];
                foreach ($warningDefinitions as $id => $warningDefinition)
                {
                    if ($warningDefinition->isUsable())
                    {
                        $phrasePairs[$id] = $warningDefinition->title;
                    }
                }
            }
        }
        else
        {
            /** @var array<int,WarningDefinitionEntity> $definitions */
            $definitions = $warningRepo->findWarningDefinitionsForList()->fetch();

            $phrasePairs = [
                0 => \XF::phrase('custom_warning'),
            ];
            foreach ($definitions as $id => $warningDefinition)
            {
                $phrasePairs[$id] = $warningDefinition->title;
            }
        }

        return $phrasePairs;
    }
}
