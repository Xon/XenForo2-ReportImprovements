<?php

namespace SV\ReportImprovements\XF\Service\Report;

use SV\ReportImprovements\XF\Entity\Report;
use SV\ReportImprovements\XF\Entity\ReportComment;
use SV\StandardLib\Helper;

/**
 * @extends \XF\Service\Report\Creator
 *
 * @property Report          $report
 * @property ReportComment   $comment
 * @property CommentPreparer $commentPreparer
 */
class Creator extends XFCP_Creator
{
    public function logIp(bool $logIp)
    {
        $this->commentPreparer->logIp($logIp);
    }

    protected function setDefaults()
    {
        $applyXFWorkAround = $this->report->report_state === 'open';
        parent::setDefaults();
        if ($applyXFWorkAround && $this->comment->state_change === 'open')
        {
            $this->comment->state_change = '';
        }

        $this->report->hydrateRelation('LastModified', $this->comment);
        $this->commentPreparer->setDisableEmbedsInUserReports(\XF::options()->svDisableEmbedsInUserReports  ?? true);
    }

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

        if (\XF::$versionId >= 2030000)
        {
            return;
        }

        if (!$this->report->exists() ||
            !$this->comment->exists())
        {
            return;
        }

        /** @var \SV\ReportImprovements\XF\Repository\Report $reportRepo */
        $reportRepo = \SV\StandardLib\Helper::repository(\XF\Repository\Report::class);
        $userIdsToAlert = $reportRepo->findUserIdsToAlertForSvReportImprov($this->report);

        /** @var Notifier $notifier */
        $notifier = Helper::service(\XF\Service\Report\Notifier::class, $this->report, $this->comment);
        $notifier->setCommentersUserIds($userIdsToAlert);
        $notifier->notify();
    }
}