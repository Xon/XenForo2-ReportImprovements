<?php

namespace SV\ReportImprovements\XF\Service\Report;

use SV\ReportImprovements\XF\Entity\Report as ExtendedReportEntity;
use SV\ReportImprovements\XF\Entity\ReportComment as ExtendedReportCommentEntity;
use SV\ReportImprovements\XF\Repository\Report;
use SV\StandardLib\Helper;
use XF\Entity\ReportComment;
use XF\Repository\Report as ReportRepo;

/**
 * @extends \XF\Service\Report\Creator
 * @property ExtendedReportEntity        $report
 * @property ExtendedReportCommentEntity $comment
 * @property CommentPreparer             $commentPreparer
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
     * @return ReportComment
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

        /** @var Report $reportRepo */
        $reportRepo = Helper::repository(ReportRepo::class);
        $userIdsToAlert = $reportRepo->findUserIdsToAlertForSvReportImprov($this->report);

        /** @var Notifier $notifier */
        $notifier = Helper::service(\XF\Service\Report\Notifier::class, $this->report, $this->comment);
        $notifier->setCommentersUserIds($userIdsToAlert);
        $notifier->notify();
    }
}