<?php

namespace SV\ReportImprovements\XF\Service\Report;


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