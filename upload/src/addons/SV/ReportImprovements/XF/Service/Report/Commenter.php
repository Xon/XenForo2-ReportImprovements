<?php

namespace SV\ReportImprovements\XF\Service\Report;

use SV\ReportImprovements\Globals;
use SV\ReportImprovements\XF\Entity\Report;

/**
 * Class Commenter
 * 
 * Extends \XF\Service\Report\Commenter
 *
 * @package SV\ReportImprovements\XF\Service\Report
 *
 * @property Report $report
 */
class Commenter extends XFCP_Commenter
{
    /**
     * @throws \Exception
     */
    public function sendNotifications()
    {
        Globals::$disableDefaultReportNotificationSvc = true;

        try
        {
            parent::sendNotifications();
        }
        finally
        {
            Globals::$disableDefaultReportNotificationSvc = null;
        }

        /** @var \SV\ReportImprovements\XF\Service\Report\Notifier $notifier */
        $notifier = $this->service('XF:Report\Notifier', $this->report, $this->comment);
        $notifier->setCommentersUserIds($this->report->commenter_user_ids);
        $notifier->setNotifyMentioned($this->commentPreparer->getMentionedUserIds());
        $notifier->notify();
    }
}