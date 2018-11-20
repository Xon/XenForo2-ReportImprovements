<?php

namespace SV\ReportImprovements\XF\Report;

use XF\Entity\Report;

/**
 * Class ProfilePostComment
 *
 * Extends \XF\Report\ProfilePostComment
 *
 * @package SV\ReportImprovements\XF\Report
 */
class ProfilePostComment extends XFCP_ProfilePostComment
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

        return $visitor->canViewProfilePostCommentReport();
    }
}