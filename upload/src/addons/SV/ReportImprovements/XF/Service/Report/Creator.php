<?php

namespace SV\ReportImprovements\XF\Service\Report;

use SV\ReportImprovements\XF\Entity\Report as ExtendedReportEntity;
use SV\ReportImprovements\XF\Entity\ReportComment as ExtendedReportCommentEntity;
use SV\ReportImprovements\XF\Repository\Report as ExtendedReportRepo;
use SV\StandardLib\Helper;
use XF\Entity\Report as ReportEntity;
use XF\Entity\ReportComment as ReportCommentEntity;
use XF\Repository\Report as ReportRepo;
use XF\Service\Report\Notifier as ReportNotifierService;
use SV\ReportImprovements\XF\Service\Report\Notifier as ExtendedReportNotifierService;

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
     * @return ReportEntity
     */
    public function getReport()
    {
        return $this->report;
    }

    /**
     * @return ReportCommentEntity
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

        /** @var ExtendedReportRepo $reportRepo */
        $reportRepo = Helper::repository(ReportRepo::class);
        $userIdsToAlert = $reportRepo->findUserIdsToAlertForSvReportImprov($this->report);

        /** @var ExtendedReportNotifierService $notifier */
        $notifier = Helper::service(ReportNotifierService::class, $this->report, $this->comment);
        $notifier->setCommentersUserIds($userIdsToAlert);
        $notifier->notify();
    }
}