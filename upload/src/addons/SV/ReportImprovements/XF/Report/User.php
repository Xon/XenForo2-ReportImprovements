<?php

namespace SV\ReportImprovements\XF\Report;

use XF\Entity\Report;

/**
 * @extends \XF\Report\User
 */
class User extends XFCP_User
{
    /**
     * @param Report $report
     * @return bool
     */
    public function canView(Report $report)
    {
        /** @var \SV\ReportImprovements\XF\Entity\Report $report */
        /** @var \SV\ReportImprovements\XF\Entity\User $visitor */
        $visitor = \XF::visitor();

        return $visitor->canViewUserReport($report);
    }

    public function getContentLink(Report $report)
    {
        $reportInfo = $report->content_info;
        if ($reportInfo && !isset($reportInfo['user_id']))
        {
            // XF1 => XF2 conversion bug
            $reportInfo['user_id'] = $report->content_id;
            $report->setTrusted('content_info', $reportInfo);
        }

        return parent::getContentLink($report);
    }
}