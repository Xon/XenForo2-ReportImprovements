<?php

namespace SV\ReportImprovements\XF\Report;

use SV\ReportImprovements\XF\Entity\Report as ExtendedReportEntity;
use SV\ReportImprovements\XF\Entity\User as ExtendedUserEntity;
use XF\Entity\Report as ReportEntity;

/**
 * @extends \XF\Report\User
 */
class User extends XFCP_User
{
    /**
     * @param ReportEntity $report
     * @return bool
     */
    public function canView(ReportEntity $report)
    {
        /** @var ExtendedReportEntity $report */
        /** @var ExtendedUserEntity $visitor */
        $visitor = \XF::visitor();

        return $visitor->canViewUserReport($report);
    }

    public function getContentLink(ReportEntity $report)
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