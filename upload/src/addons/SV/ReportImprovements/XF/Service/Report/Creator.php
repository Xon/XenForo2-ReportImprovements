<?php

namespace SV\ReportImprovements\XF\Service\Report;



/**
 * Extends \XF\Service\Report\Creator
 */
class Creator extends XFCP_Creator
{
    /**
     * @throws \Exception
     */
    public function sendNotifications()
    {
        parent::sendNotifications();

        if (!$this->report->exists())
        {
            return;
        }

        $comment = $this->commentPreparer->getComment();
        if (!$comment)
        {
            return;
        }

        /** @var \SV\ReportImprovements\XF\Repository\Report $reportRepo */
        $reportRepo = $this->repository('XF:Report');
        $usersToAlert = $reportRepo->findUsersToAlertForSvReportImprov($this->report);
        $userIdsToAlert = $usersToAlert->keys();

        /** @var \SV\ReportImprovements\XF\Service\Report\Notifier $notifier */
        $notifier = $this->service('XF:Report\Notifier', $this->report, $comment);
        $notifier->setCommentersUserIds($userIdsToAlert);
        $notifier->notify();
    }
}