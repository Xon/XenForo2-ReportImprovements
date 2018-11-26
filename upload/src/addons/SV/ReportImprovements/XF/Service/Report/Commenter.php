<?php

namespace SV\ReportImprovements\XF\Service\Report;

use SV\ReportImprovements\XF\Entity\Report;
use SV\ReportImprovements\XF\Entity\ReportComment;

/**
 * Class Commenter
 *
 * Extends \XF\Service\Report\Commenter
 *
 * @package SV\ReportImprovements\XF\Service\Report
 *
 * @property Report $report
 * @property ReportComment $comment
 */
class Commenter extends XFCP_Commenter
{
    protected function finalSetup()
    {
        $sendAlert = $this->sendAlert;
        $this->sendAlert = false;

        if ($sendAlert)
        {
            $this->comment->alertSent = true;
            $this->comment->alertComment = $this->alertComment;
        }

        parent::finalSetup();

        $this->sendAlert = $sendAlert;
    }

    /**
     * @throws \Exception
     */
    public function sendNotifications()
    {
        parent::sendNotifications();

        $comment = $this->comment;

        /** @var \SV\ReportImprovements\XF\Repository\Report $reportRepo */
        $reportRepo = $this->repository('XF:Report');
        $usersToAlert = $reportRepo->findUsersToAlertForSvReportImprov($comment);
        $userIdsToAlert = $usersToAlert->keys();

        /** @var \SV\ReportImprovements\XF\Service\Report\Notifier $notifier */
        $notifier = $this->service('XF:Report\Notifier', $this->report, $comment);
        $notifier->setCommentersUserIds($userIdsToAlert);
        $notifier->notify();
    }
}