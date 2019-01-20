<?php

namespace SV\ReportImprovements\XF\Repository;

use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Entity;

/**
 * Class Report
 *
 * Extends \XF\Repository\Report
 *
 * @package SV\ReportImprovements\XF\Repository
 */
class Report extends XFCP_Report
{
    /**
     * @param array $state
     * @param int|null  $timeFrame
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
     * @return ArrayCollection
     * @throws \Exception
     */
    public function getModeratorsWhoCanHandleReport(\XF\Entity\Report $report)
    {
        $nodeId = null;
        if (isset($report->content_info['node_id']))
        {
            $nodeId = $report->content_info['node_id'];
            $this->app()->em()->find('XF:Forum', $nodeId);
        }

        /** @var \XF\Repository\Moderator $moderatorRepo */
        $moderatorRepo = $this->repository('XF:Moderator');

        $moderators = $moderatorRepo->findModeratorsForList()->with('User.PermissionCombination')->fetch();

        if ($moderators->count())
        {
            /**
             * @var int $id
             * @var \XF\Entity\Moderator $moderator
             */
            if ($nodeId)
            {
                $permCombinationIds = [];
                foreach ($moderators AS $id => $moderator)
                {
                    $id = $moderator->User->permission_combination_id;
                    $permCombinationIds[$id] = $id;
                }
                $this->app()->permissionCache()->cacheMultipleContentPermsForContent($permCombinationIds, 'node', $nodeId);
            }

            foreach ($moderators AS $id => $moderator)
            {
                $canView = \XF::asVisitor($moderator->User,
                    function() use ($report) { return $report->canView(); }
                );
                if (!$canView)
                {
                    unset($moderators[$id]);
                }
            }
        }

        return $moderators;
    }

    /**
     * @param \XF\Mvc\Entity\ArrayCollection $reports
     *
     * @return \XF\Mvc\Entity\ArrayCollection
     */
    public function filterViewableReports($reports)
    {
        $em = $this->app()->em();
        $nodeIds = [];
        /** @var \XF\Entity\Report $report */
        foreach($reports as $report)
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

        // assume fixed in 2.0.12+  and 2.1.0 beta 5+
        if ((\XF::$versionId > 2001270 && \XF::$versionId < 2010000) || (\XF::$versionId > 2010000 && \XF::$versionId > 2010035))
        {
            /** @noinspection PhpDeprecationInspection */
            $reports = parent::filterViewableReports($reports);
        }
        else
        {
            $reports = $reports->filterViewable();
        }

        $userIds = [];
        /** @var \XF\Entity\Report $report */
        foreach($reports as $report)
        {
            $userIds[$report->content_user_id] = true;
            $userIds[$report->assigned_user_id] = true;
            $userIds[$report->last_modified_user_id] = true;
        }

        foreach($userIds as $userId => $null)
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
     *
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
        }

        return $userIds ?: [];
    }

	protected $userReportCountCache = null;

    /**
     * @param \XF\Entity\User|\SV\ReportImprovements\XF\Entity\User $user
     * @param int $daysLimit
     * @param string $state
     *
     * @return mixed
     */
    public function countReportsByUser(\XF\Entity\User $user, $daysLimit, $state = '')
    {
        if ($this->userReportCountCache === null)
        {
            $this->userReportCountCache = [];
        }

        if (!isset($this->userReportCountCache[$user->user_id][$state][$daysLimit]))
        {
            $db = $this->db();

            $params = [$user->user_id];
            $additionalWhere = '';
            if ($daysLimit)
            {
                $params[] = \XF::$time - (86400 * $daysLimit);
                $additionalWhere = 'AND report_comment.comment_date ?';
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
                $stats[$reportStat['report_state']] = $reportStat['count'];
            }
            $stats[''] = $total;
            $this->userReportCountCache[$user->user_id][$daysLimit] = $stats;
        }

        if (isset($this->userReportCountCache[$user->user_id][$state][$daysLimit]))
        {
            return $this->userReportCountCache[$user->user_id][$state][$daysLimit];
        }

        return 0;
    }
}