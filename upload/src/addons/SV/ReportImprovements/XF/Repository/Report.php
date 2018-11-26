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
                if (!$em->findCached('XF:Forum', $nodeId))
                {
                    $nodeIds[$nodeId] = true;
                }
            }
        }

        if ($nodeIds)
        {
            $em->findByIds('XF:Forum', array_keys($nodeIds));
        }

        $reports = parent::filterViewableReports($reports);

        $userIds = [];
        /** @var \XF\Entity\Report $report */
        foreach($reports as $report)
        {
            $userIds[$report->content_user_id] = true;
            $userIds[$report->assigned_user_id] = true;
            $userIds[$report->last_modified_user_id] = true;
            if (isset($report->content_info['node_id']))
            {
                $nodeIds[$report->content_info['node_id']] = true;
            }
        }

        foreach($userIds as $userId => $null)
        {
            if ($em->findCached('XF:User', $userId))
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
     * @param Entity|\XF\Entity\Report|\XF\Entity\ReportComment $entity
     *
     * @return ArrayCollection
     */
    public function findUsersToAlertForSvReportImprov(Entity $entity)
    {
        $users = [];

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
                        $users[$moderator->user_id] = $moderator->User;
                    }
                }
            }
        }
        else if ($entity instanceof \XF\Entity\ReportComment)
        {
            $userIds = [];
            if ($this->options()->sv_report_alert_mode !== 'always_alert')
            {
                $db = $this->db();
                $userIds = $db->fetchAllColumn('
                    SELECT DISTINCT user_id
                    FROM xf_report_comment
                    WHERE report_id = ?
                      AND user_id <> ?
                ', [$entity->report_id, $entity->user_id]);

                $userIds = array_column($userIds, 'user_id');
            }

            if (\count($userIds))
            {
                $users = $this->finder('XF:User')
                    ->where('user_id', $userIds)
                    ->fetch();
            }
        }

        return new ArrayCollection($users);
    }
	
	protected $userReportCountCache = null;

    /**
     * @param User $user
     * @param int $daysLimit
     * @param string $state
     *
     * @return mixed
     */
    public function countReportsByUser(User $user, $daysLimit, $state = '')
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