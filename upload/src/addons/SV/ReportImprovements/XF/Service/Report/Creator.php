<?php

namespace SV\ReportImprovements\XF\Service\Report;

/**
 * Extends \XF\Service\Report\Creator
 */
class Creator extends XFCP_Creator
{
    /**
     * @return \XF\Entity\Report
     */
    public function getReport()
    {
        return $this->report;
    }

    /**
     * @return \XF\Entity\ReportComment
     */
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * @throws \Exception
     */
    public function sendNotifications()
    {
        parent::sendNotifications();

        if (!$this->report->exists() ||
            !$this->comment->exists())
        {
            return;
        }

        /** @var \SV\ReportImprovements\XF\Repository\Report $reportRepo */
        $reportRepo = $this->repository('XF:Report');
        $userIdsToAlert = $reportRepo->findUserIdsToAlertForSvReportImprov($this->report);

        /** @var \SV\ReportImprovements\XF\Service\Report\Notifier $notifier */
        $notifier = $this->service('XF:Report\Notifier', $this->report, $this->comment);
        $notifier->setCommentersUserIds($userIdsToAlert);
        $notifier->notify();
    }
}