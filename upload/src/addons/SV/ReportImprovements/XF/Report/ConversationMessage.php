<?php

namespace SV\ReportImprovements\XF\Report;

use XF\Entity\Report;

/**
 * Class ConversationMessage
 *
 * Extends \XF\Report\ConversationMessage
 *
 * @package SV\ReportImprovements\XF\Report
 */
class ConversationMessage extends XFCP_ConversationMessage
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

        if (!$visitor->canViewConversationMessageReport($error))
        {
            return false;
        }

        return parent::canView($report);
    }
}