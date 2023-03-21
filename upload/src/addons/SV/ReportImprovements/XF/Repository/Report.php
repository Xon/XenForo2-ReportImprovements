<?php

namespace SV\ReportImprovements\XF\Repository;

use XF\Db\Exception;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Entity;
use XF\Report\AbstractHandler;
use function sort;

/**
 * Class Report
 * Extends \XF\Repository\Report
 *
 * @package SV\ReportImprovements\XF\Repository
 */
class Report extends XFCP_Report
{
    public function svPreloadReportComments(AbstractCollection $reportComments)
    {
        $reports = [];
        /** @var \SV\ReportImprovements\XF\Entity\ReportComment $reportComment */
        foreach ($reportComments as $reportComment)
        {
            $reports[$reportComment->report_id] = $reportComment->Report;
        }

        $this->svPreloadReports(new ArrayCollection($reports));
    }

    public function svPreloadReports(AbstractCollection $reports)
    {
        $reportsByContentType = [];
        /** @var \SV\ReportImprovements\XF\Entity\Report $report */
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
            $contentIds = \array_keys($reports);
            if (!$contentIds)
            {
                continue;
            }
            $reportContents = $handler->getContent($contentIds);
            foreach ($reportContents as $contentId => $reportContent)
            {
                /** @var \SV\ReportImprovements\XF\Entity\Report $report */
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

    /** @noinspection PhpUnusedParameterInspection */
    protected function getReportAssignableNonModeratorsCacheTime(int $reportQueueId): int
    {
        return 86400; // 1 day
    }

    protected function getReportAssignableNonModeratorsCacheKey(int $reportQueueId): string
    {
        return 'reports-non-mods-assignable-' . $reportQueueId;
    }

    public function deferResetNonModeratorsWhoCanHandleReportCache()
    {
        \XF::runLater(function(){
            $this->resetNonModeratorsWhoCanHandleReportCache();
        });
    }

    public function resetNonModeratorsWhoCanHandleReportCache()
    {
        $cache = \XF::app()->cache();
        if ($cache === null)
        {
            return;
        }

        /** @var \SV\ReportImprovements\Repository\ReportQueue $entryRepo */
        $entryRepo = $this->repository('SV\ReportImprovements:ReportQueue');
        /** @var int[] $reportQueueIds */
        $reportQueueIds = $entryRepo->getReportQueueList()->keys();
        $reportQueueIds[] = 0;

        foreach($reportQueueIds as $reportQueueId)
        {
            $key = $this->getReportAssignableNonModeratorsCacheKey($reportQueueId);
            if ($key)
            {
                $cache->delete($key);
            }
        }
    }

    /**
     * @param \XF\Entity\Report $report
     * @param bool              $doCache
     * @return int[]
     * @throws Exception
     * @noinspection PhpDocMissingThrowsInspection
     * @noinspection SqlResolve
     */
    public function getNonModeratorsWhoCanHandleReport(\XF\Entity\Report $report, bool $doCache = true)
    {
        $reportQueueId = (int)($report->queue_id ?? 0);
        $key = $this->getReportAssignableNonModeratorsCacheKey($reportQueueId);
        $cacheTime = $this->getReportAssignableNonModeratorsCacheTime($reportQueueId);
        $cache = \XF::app()->cache();

        $userIds = null;
        if ($doCache && $cache && $key && $cacheTime)
        {
            $userIds = @$cache->fetch($key);
            $userIds = \is_array($userIds) ? $userIds : null;
        }

        // apply sanity check limit <= 0 means no limit. WHY
        $options = \XF::options();
        $limit = (int)($options->svNonModeratorReportHandlingLimit ?? 1000);
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
                        \trigger_error($error, E_USER_WARNING);
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
                STRAIGHT_JOIN xf_user AS xu ON xu.user_id = a.user_id
                WHERE xu.is_moderator = 0 AND xu.user_state = 'valid'
                ON DUPLICATE KEY UPDATE
                    canView = if(canView = 0 OR a.permission_value = 'never' OR a.permission_value = 'reset', 0, if(a.permission_value = 'allow', 1, NULL))
            ");
            $db->query("
                INSERT INTO xf_sv_non_moderator_report_users (user_id, canView)
                SELECT DISTINCT userPerm.user_id, if(permission_value = 'allow', 1, 0) as val
                FROM xf_permission_entry AS userPerm use index (permission_group_id_permission_id)
                STRAIGHT_JOIN xf_user AS xu ON userPerm.user_id = xu.user_id
                WHERE xu.is_moderator = 0 AND xu.user_state = 'valid' AND
                      userPerm.permission_group_id = 'general' AND userPerm.permission_id = 'viewReports'
                ON DUPLICATE KEY UPDATE
                    canView = if(canView = 0 OR userPerm.permission_value = 'never' OR userPerm.permission_value = 'reset', 0, if(userPerm.permission_value = 'allow', 1, NULL))
            ");
            // prune the set
            $db->query("
                DELETE reportUsers
                FROM xf_sv_non_moderator_report_users AS reportUsers
                WHERE canView = 0 or canView is null or user_id = 0
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

                $db->query("
                    DELETE reportUsers
                    FROM xf_sv_non_moderator_report_users AS reportUsers
                    WHERE canView = 0 or canView is null
                ");
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

        $count = \count($userIds);
        if ($limit && $count > $limit)
        {
            $error = "Potential miss-configuration detected. {$count} users have access to handle/update this report via permissions. Sanity limit is {$limit}, to adjust edit the 'Maximum non-moderator users who can handle reports' option";
            if (\XF::$debugMode)
            {
                \trigger_error($error, E_USER_WARNING);
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
     */
    public function getModeratorsWhoCanHandleReport(\XF\Entity\Report $report)
    {
        /** @var \SV\ReportImprovements\XF\Entity\Report $report */
        $nodeId = (int)($report->content_info['node_id'] ?? 0);
        if ($nodeId !== 0)
        {
            $this->app()->em()->find('XF:Forum', $nodeId);
        }

        /** @var \XF\Repository\Moderator $moderatorRepo */
        $moderatorRepo = $this->repository('XF:Moderator');

        /** @var \XF\Entity\Moderator[] $moderators */
        $moderators = $moderatorRepo->findModeratorsForList()
                                    ->with('User.PermissionCombination')
                                    ->fetch()
                                    ->toArray();

        $permCombinationIds = [];
        foreach ($moderators AS $moderator)
        {
            $id = $moderator->User->permission_combination_id;
            $permCombinationIds[$id] = $id;
        }

        $fakeModerators = $this->getNonModeratorsWhoCanHandleReport($report);
        if ($fakeModerators)
        {
            $users = \XF::finder('XF:User')
                        ->with('PermissionCombination')
                        ->where('user_id', '=', $fakeModerators)
                        ->fetch();
            $em = \XF::em();
            /** @var \XF\Entity\User $user */
            foreach ($users as $user)
            {
                $id = $user->permission_combination_id;
                $permCombinationIds[$id] = $id;

                /** @var \XF\Entity\Moderator $moderator */
                $moderator = $em->create('XF:Moderator');
                $moderator->setTrusted('user_id', $user->user_id);
                $moderator->hydrateRelation('User', $user);
                $moderator->setReadOnly(true);

                $moderators[] = $moderator;
            }
        }

        if (\count($permCombinationIds) !== 0)
        {
            $permCombinationIds = \array_keys($permCombinationIds);
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

        foreach ($moderators AS $id => $moderator)
        {
            /** @var int $id */
            $canView = \XF::asVisitor($moderator->User,
                function () use ($report) { return $report->canView() && $report->canUpdate(); }
            );
            if (!$canView)
            {
                unset($moderators[$id]);
            }
        }

        return new ArrayCollection($moderators);
    }

    /**
     * @param AbstractCollection $reports
     * @return AbstractCollection
     */
    public function filterViewableReports($reports)
    {
        $em = $this->app()->em();

        // avoid N+1 look up behaviour, just cache all node perms
        \XF::visitor()->cacheNodePermissions();

        // pre-XF2.0.12 and a few versions of XF2.1 have a bug where they skip Report::canView check, and XF2.1 marks it as deprecated
        $reports = $reports->filterViewable();

        $userIds = [];
        /** @var \XF\Entity\Report $report */
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
            $em->findByIds('XF:User', \array_keys($userIds));
        }

        return $reports;
    }

    /**
     * @param Entity|\XF\Entity\Report|\XF\Entity\ReportComment $entity
     * @return int[]
     * @noinspection PhpDocMissingThrowsInspection
     */
    public function findUserIdsToAlertForSvReportImprov(Entity $entity)
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
                    /** @var \XF\Entity\Moderator $moderator */
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

            /** @var \SV\ReportImprovements\XF\Entity\Report $report */
            $report = $entity->Report;
            if ($entity->state_change === 'assigned' && $report->assigned_user_id)
            {
                // alerts the assigned user who likely isn't a watcher
                $userIds[] = $report->assigned_user_id;
                $userIds = \array_unique($userIds);
            }
        }

        return $userIds ?: [];
    }

    protected $userReportCountCache = null;

    /**
     * @param \XF\Entity\User|\SV\ReportImprovements\XF\Entity\User $user
     * @param int                                                   $daysLimit
     * @param string                                                $state
     * @return int
     */
    public function countReportsByUser(\XF\Entity\User $user, int $daysLimit, string $state = '')
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
         * @var int               $reportId
         * @var \XF\Entity\Report $report
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
        $states = $structure->columns['report_state']['allowedValues'] ?? [];
        assert(is_array($states) && count($states) > 0);

        return $states;
    }

    /**
     * @return array<string,\XF\Phrase>
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
     * @return array<string,array{handler: AbstractHandler, phrase: \XF\Phrase}>
     */
    public function getReportTypes(): array
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
            $contentTypes[$contentType] = [
                'handler' => $handler,
                'phrase'  => $app->getContentTypePhrase($contentType),
                'phrases'  => $app->getContentTypePhrase($contentType, true),
            ];
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
}
