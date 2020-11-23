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

    /**
     * @param \XF\Entity\Report $report
     * @return int[]
     * @noinspection PhpUnusedParameterInspection
     */
    protected function getNonModeratorsWhoCanHandleReport(\XF\Entity\Report $report)
    {
        // apply sanity check limit <= 0 means no limit. WHY
        $options = \XF::options();
        $limit = isset($options->svNonModeratorReportHandlingLimit) ? (int)$options->svNonModeratorReportHandlingLimit : 1000;
        $limit = max(0, $limit);
        $db = \XF::db();

        // find users with groups with the update report, or via direct permission assignment but aren't moderators
        // ensure they can view the report centre, or this might return more users than expected
        // SQL note; This query produces vastly faster query plans as it goes from what should be a small set of permission rows to users rather than all users and then filtering down
        $userIds = $db->fetchAllColumn("
            SELECT DISTINCT xu.user_id 
            FROM (
                SELECT DISTINCT innerGroupPerm.user_group_id 
                FROM xf_permission_entry as innerGroupPerm
                WHERE innerGroupPerm.permission_group_id = 'general' AND innerGroupPerm.permission_id = 'viewReports' AND innerGroupPerm.permission_value = 'allow' 
            ) a 
            JOIN xf_user_group_relation gr ON a.user_group_id = gr.user_group_id
            JOIN xf_user xu ON gr.user_id = xu.user_id
            WHERE xu.is_moderator = 0 and xu.user_state = 'valid' AND (
                  exists(SELECT outerUserPerm.user_id 
                        FROM xf_permission_entry as outerUserPerm
                        WHERE outerUserPerm.user_id = xu.user_id AND 
                              outerUserPerm.permission_group_id = 'general' AND outerUserPerm.permission_id = 'updateReport' AND outerUserPerm.permission_value = 'allow') OR 
                  exists(SELECT gr.user_id
                  
                        FROM xf_permission_entry as outerUserPerm
                        JOIN xf_user_group_relation gr ON outerUserPerm.user_group_id = gr.user_group_id
                        WHERE gr.user_id = xu.user_id AND 
                              outerUserPerm.permission_group_id = 'general' AND outerUserPerm.permission_id = 'updateReport' AND outerUserPerm.permission_value = 'allow')
                  )
            UNION
            SELECT DISTINCT innerUserGroupPerm.user_id 
            FROM xf_permission_entry as innerUserGroupPerm
            JOIN xf_user xu ON innerUserGroupPerm.user_id = xu.user_id
            WHERE innerUserGroupPerm.permission_group_id = 'general' AND innerUserGroupPerm.permission_id = 'viewReports' AND innerUserGroupPerm.permission_value = 'allow' AND
                  xu.is_moderator = 0 and xu.user_state = 'valid' AND (
                  exists(SELECT outerUserPerm.user_id 
                        FROM xf_permission_entry as outerUserPerm
                        WHERE outerUserPerm.user_id = xu.user_id AND 
                              outerUserPerm.permission_group_id = 'general' AND outerUserPerm.permission_id = 'updateReport' AND outerUserPerm.permission_value = 'allow') OR 
                  exists(SELECT gr.user_id
                  
                        FROM xf_permission_entry as outerUserPerm
                        JOIN xf_user_group_relation gr ON outerUserPerm.user_group_id = gr.user_group_id
                        WHERE gr.user_id = xu.user_id AND 
                              outerUserPerm.permission_group_id = 'general' AND outerUserPerm.permission_id = 'updateReport' AND outerUserPerm.permission_value = 'allow')
                  )
        ");

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
        $nodeIds = [];
        /** @var \XF\Entity\Report $report */
        foreach ($reports as $report)
        {
            if (isset($report->content_info['node_id']))
            {
                $nodeId = $report->content_info['node_id'];
                if ($nodeId && !$em->findCached('XF:Forum', $nodeId))
                {
                    $nodeIds[$nodeId] = true;
                }
            }
        }

        if ($nodeIds)
        {
            $em->findByIds('XF:Forum', array_keys($nodeIds));
        }

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