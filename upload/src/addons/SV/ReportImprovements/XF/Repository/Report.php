<?php

namespace SV\ReportImprovements\XF\Repository;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Entity;

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
            $contentIds = array_keys($reports);
            if (!$contentIds)
            {
                continue;
            }
            $reportContents = $handler->getContent($contentIds);
            foreach ($reportContents as $contentId => $reportContent)
            {
                if (empty($reportsByContentType[$contentType][$contentId]))
                {
                    continue;
                }

                /** @var \SV\ReportImprovements\XF\Entity\Report $report */
                $report = $reportsByContentType[$contentType][$contentId];

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

    protected function getReportAssignableNonModeratorsCacheTime()
    {
        return 86400; // 1 day
    }

    protected function getReportAssignableNonModeratorsCacheKey()
    {
        return 'reports-non-mods-assignable';
    }

    public function deferResetNonModeratorsWhoCanHandleReportCache()
    {
        \XF::runLater(function(){
            $this->resetNonModeratorsWhoCanHandleReportCache();
        });
    }

    public function resetNonModeratorsWhoCanHandleReportCache()
    {
        $key = $this->getReportAssignableNonModeratorsCacheKey();
        $cache = \XF::app()->cache();
        if ($cache && $key)
        {
            $cache->delete($key);
        }
    }

    /**
     * @param \XF\Entity\Report $report
     * @return int[]
     * @noinspection PhpUnusedParameterInspection
     */
    protected function getNonModeratorsWhoCanHandleReport(\XF\Entity\Report $report)
    {
        $key = $this->getReportAssignableNonModeratorsCacheKey();
        $cacheTime = (int)$this->getReportAssignableNonModeratorsCacheTime();
        $cache = \XF::app()->cache();

        $userIds = null;
        if ($cache && $key && $cacheTime)
        {
            $userIds = @\json_decode($cache->fetch($key), true);
            $userIds = is_array($userIds) ? $userIds : null;
        }

        // apply sanity check limit <= 0 means no limit. WHY
        $options = \XF::options();
        $limit = isset($options->svNonModeratorReportHandlingLimit) ? (int)$options->svNonModeratorReportHandlingLimit : 1000;
        $limit = max(0, $limit);

        if (!is_array($userIds))
        {
            // find users with groups with the update report, or via direct permission assignment but aren't moderators
            // ensure they can view the report centre, or this might return more users than expected
            // this requires an index on xf_permission_entry.permission_group_id/xf_permission_entry.permission_id to be effective
            // this can still be slow-ish initially, so cache.
            // Note; to avoid catastrophically poor performance in older versions of MySQL, do this incrementally via explicit temp tables

            $db = \XF::db();
            $db->query('DROP TABLE IF EXISTS xf_sv_non_moderator_report_users_view');
            $db->query('DROP TABLE IF EXISTS xf_sv_non_moderator_report_users_update');

            // canView/canUpdate values (in order of priority);
            // 0    - denied
            // 1    - allowed
            // null - not yet denied
            $db->query('
            CREATE TEMPORARY TABLE xf_sv_non_moderator_report_users_view (
                user_id int UNSIGNED NOT NULL PRIMARY KEY,
                canView tinyint(10) DEFAULT NULL
            )');
            $db->query('
            CREATE TEMPORARY TABLE xf_sv_non_moderator_report_users_update (
                user_id int UNSIGNED NOT NULL PRIMARY KEY,
                canUpdate tinyint(10) DEFAULT NULL
            )');
            // build the initial list of users who can view reports
            $db->query("
                INSERT INTO xf_sv_non_moderator_report_users_view (user_id, canView)
                SELECT DISTINCT gr.user_id, if(permission_value = 'allow', 1, 0)
                FROM xf_permission_entry AS groupPerm
                JOIN xf_user_group_relation AS gr ON groupPerm.user_group_id = gr.user_group_id
                JOIN xf_user AS xu ON gr.user_id = xu.user_id
                WHERE xu.is_moderator = 0 AND xu.user_state = 'valid' AND
                     groupPerm.permission_group_id = 'general' AND groupPerm.permission_id = 'viewReports'
                ON DUPLICATE KEY UPDATE
                    canView = if(canView = 0 OR groupPerm.permission_value = 'never', 0, if(groupPerm.permission_value = 'allow', 1, NULL))
            ");
            $db->query("
                INSERT INTO xf_sv_non_moderator_report_users_view (user_id, canView)
                SELECT DISTINCT userPerm.user_id, if(permission_value = 'allow', 1, 0)
                FROM xf_permission_entry AS userPerm
                JOIN xf_user AS xu ON userPerm.user_id = xu.user_id
                WHERE xu.is_moderator = 0 AND xu.user_state = 'valid' AND
                      userPerm.permission_group_id = 'general' AND userPerm.permission_id = 'viewReports'
                ON DUPLICATE KEY UPDATE
                    canView = if(canView = 0 OR userPerm.permission_value = 'never', 0, if(userPerm.permission_value = 'allow', 1, NULL))
            ");
            // prune the set
            $db->query("
                DELETE reportUsers
                FROM xf_sv_non_moderator_report_users_view AS reportUsers
                WHERE canView = 0 or canView is null
            ");

            // merge in the list of users who can update reports
            $db->query("
                INSERT INTO xf_sv_non_moderator_report_users_update (user_id, canUpdate)
                SELECT DISTINCT groupPerm.user_id, if(permission_value = 'allow', 1, 0)
                FROM xf_permission_entry AS groupPerm
                JOIN xf_user_group_relation AS gr ON groupPerm.user_group_id = gr.user_group_id
                JOIN xf_sv_non_moderator_report_users_view AS reportUsers ON reportUsers.user_id = gr.user_id
                WHERE groupPerm.permission_group_id = 'general' AND groupPerm.permission_id = 'updateReport'
                ON DUPLICATE KEY UPDATE
                    canUpdate = if(canUpdate = 0 OR groupPerm.permission_value = 'never', 0, if(groupPerm.permission_value = 'allow', 1, NULL))
            ");
            $db->query("
                INSERT INTO xf_sv_non_moderator_report_users_update (user_id, canUpdate)
                SELECT DISTINCT userPerm.user_id, if(permission_value = 'allow', 1, 0)
                FROM xf_permission_entry AS userPerm
                JOIN xf_sv_non_moderator_report_users_view AS reportUsers ON reportUsers.user_id = userPerm.user_id
                WHERE userPerm.permission_group_id = 'general' AND userPerm.permission_id = 'updateReport'
                ON DUPLICATE KEY UPDATE
                    canUpdate = if(canUpdate = 0 OR userPerm.permission_value = 'never', 0, if(userPerm.permission_value = 'allow', 1, NULL))
            ");

            $userIds = $db->fetchAllColumn('SELECT user_id FROM xf_sv_non_moderator_report_users_update where canUpdate = 1 and user_id <> 0');


//            $userIds = \XF::db()->fetchAllColumn("
//                SELECT xu.user_id
//                FROM xf_user xu
//                WHERE xu.is_moderator = 0 AND xu.user_state = 'valid' AND (
//                      NOT exists(SELECT notExistsGroupPerm.user_id
//                            FROM xf_permission_entry AS notExistsGroupPerm
//                            WHERE notExistsGroupPerm.user_id = xu.user_id AND
//                                  notExistsGroupPerm.permission_group_id = 'general' AND notExistsGroupPerm.permission_id = 'viewReports' AND notExistsGroupPerm.permission_value = 'never') OR
//                      NOT exists(SELECT gr.user_id
//                            FROM xf_permission_entry AS notExistsUserPerm
//                            JOIN xf_user_group_relation gr ON notExistsUserPerm.user_group_id = gr.user_group_id
//                            WHERE gr.user_id = xu.user_id AND
//                                  notExistsUserPerm.permission_group_id = 'general' AND notExistsUserPerm.permission_id = 'viewReports' AND notExistsUserPerm.permission_value = 'never')
//                      ) AND (
//                      exists(SELECT existsUserPerm.user_id
//                            FROM xf_permission_entry AS existsUserPerm
//                            WHERE existsUserPerm.user_id = xu.user_id AND
//                                  existsUserPerm.permission_group_id = 'general' AND existsUserPerm.permission_id = 'viewReports' AND existsUserPerm.permission_value = 'allow') OR
//                      exists(SELECT gr.user_id
//                            FROM xf_permission_entry AS existsUserPerm
//                            JOIN xf_user_group_relation gr ON existsUserPerm.user_group_id = gr.user_group_id
//                            WHERE gr.user_id = xu.user_id AND
//                                  existsUserPerm.permission_group_id = 'general' AND existsUserPerm.permission_id = 'viewReports' AND existsUserPerm.permission_value = 'allow')
//                      ) AND (
//                      NOT exists(SELECT notExistsUserPerm.user_id
//                            FROM xf_permission_entry AS notExistsUserPerm
//                            WHERE notExistsUserPerm.user_id = xu.user_id AND
//                                  notExistsUserPerm.permission_group_id = 'general' AND notExistsUserPerm.permission_id = 'updateReport' AND notExistsUserPerm.permission_value = 'never') OR
//                      NOT exists(SELECT gr.user_id
//                            FROM xf_permission_entry AS notExistsGroupPerm
//                            JOIN xf_user_group_relation gr ON notExistsGroupPerm.user_group_id = gr.user_group_id
//                            WHERE gr.user_id = xu.user_id AND
//                                  notExistsGroupPerm.permission_group_id = 'general' AND notExistsGroupPerm.permission_id = 'updateReport' AND notExistsGroupPerm.permission_value = 'never')
//                      ) AND (
//                      exists(SELECT existsUserPerm.user_id
//                            FROM xf_permission_entry AS existsUserPerm
//                            WHERE existsUserPerm.user_id = xu.user_id AND
//                                  existsUserPerm.permission_group_id = 'general' AND existsUserPerm.permission_id = 'updateReport' AND existsUserPerm.permission_value = 'allow') OR
//                      exists(SELECT gr.user_id
//                            FROM xf_permission_entry AS existsGroupPerm
//                            JOIN xf_user_group_relation gr ON existsGroupPerm.user_group_id = gr.user_group_id
//                            WHERE gr.user_id = xu.user_id AND
//                                  existsGroupPerm.permission_group_id = 'general' AND existsGroupPerm.permission_id = 'updateReport' AND existsGroupPerm.permission_value = 'allow')
//                      )
//            ");

            if ($cache && $key && $cacheTime)
            {
                $cache->save($key, \json_encode($userIds), $cacheTime);
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
     */
    public function getModeratorsWhoCanHandleReport(\XF\Entity\Report $report)
    {
        /** @var \SV\ReportImprovements\XF\Entity\Report $report */
        $nodeId = null;
        if (isset($report->content_info['node_id']))
        {
            $nodeId = $report->content_info['node_id'];
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
        if ($nodeId)
        {
            foreach ($moderators AS $id => $moderator)
            {
                $id = $moderator->User->permission_combination_id;
                $permCombinationIds[$id] = $id;
            }
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

        if ($permCombinationIds && $nodeId)
        {
            $this->app()->permissionCache()->cacheMultipleContentPermsForContent($permCombinationIds, 'node', $nodeId);
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
     * @noinspection PhpMissingParamTypeInspection
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
     * @noinspection PhpDocMissingThrowsInspection
     * @param Entity|\XF\Entity\Report|\XF\Entity\ReportComment $entity
     * @return int[]
     */
    public function findUserIdsToAlertForSvReportImprov(Entity $entity)
    {
        $userIds = [];
        if ($this->options()->sv_report_alert_mode === 'none')
        {
            return $userIds;
        }

        if ($entity instanceof \XF\Entity\Report)
        {
            if ($this->options()->sv_report_alert_mode !== 'watchers')
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
            if ($this->options()->sv_report_alert_mode !== 'always_alert')
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
        foreach ($reports AS $reportId => $report)
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
}
