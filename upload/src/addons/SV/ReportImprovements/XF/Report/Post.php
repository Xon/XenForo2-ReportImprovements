<?php

namespace SV\ReportImprovements\XF\Report;

use XF\Entity\Report;

/**
 * Class Post
 * 
 * Extends \XF\Report\Post
 *
 * @package SV\ReportImprovements\XF\Report
 */
class Post extends XFCP_Post
{
    /**
     * @param Report $report
     *
     * @return bool
     */
    public function canView(Report $report)
    {
        /** @var \SV\ReportImprovements\XF\Entity\User $visitor */
        $visitor = \XF::visitor();
        $nodeId = $report->content_info['node_id'];

        if (!$visitor->hasNodePermission($nodeId, 'viewReportPost'))
        {
            return false;
        }

        return parent::canView($report);
    }
}