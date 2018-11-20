<?php

namespace SV\ReportImprovements\XF\Repository;

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
}