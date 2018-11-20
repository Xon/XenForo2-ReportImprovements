<?php

namespace SV\ReportImprovements\XF\Report;

use XF\Entity\Report;

/**
 * Class User
 *
 * Extends \XF\Report\User
 *
 * @package SV\ReportImprovements\XF\Report
 */
class User extends XFCP_User
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

        return $visitor->canViewUserReport();
    }
}