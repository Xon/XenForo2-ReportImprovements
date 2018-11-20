<?php

namespace SV\ReportImprovements\XF\Report;

use XF\Entity\Report;

/**
 * Class ProfilePost
 * 
 * Extends \XF\Report\ProfilePost
 *
 * @package SV\ReportImprovements\XF\Report
 */
class ProfilePost extends XFCP_ProfilePost
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

        if (!$visitor->canViewProfilePostReport($error))
        {
            return false;
        }

        return parent::canView($report);
    }
}