<?php

namespace SV\ReportImprovements\XF\Service\Report;

use SV\ReportImprovements\XF\Entity\Report as ExtendedReportEntity;
use SV\ReportImprovements\XF\Entity\ReportComment as ExtendedReportCommentEntity;
use SV\StandardLib\Helper;
use XF\Behavior\Indexable;
use XF\Behavior\IndexableContainer;
use XF\Mvc\Entity\Entity;
use XF\Repository\Ip as IpRepo;
use XF\Service\Attachment\Preparer as AttachmentPreparerSvc;
use XF\Service\Message\Preparer as MessagePreparerService;
use function assert;
use function in_array;
use function strlen;

/**
 * @extends \XF\Service\Report\CommentPreparer
 *
 * @property ExtendedReportEntity        $report
 * @property ExtendedReportCommentEntity $comment
 */
class CommentPreparer extends XFCP_CommentPreparer
{
    /** @var MessagePreparerService */
    protected $preparer;

    /**  @var string|null  */
    protected $attachmentHash;

    /** @var bool */
    protected $logIp = false;

    /** @var bool */
    protected $disableEmbedsInUserReports = false;

    public function logIp(bool $logIp)
    {
        $this->logIp = $logIp;
    }

    /**
     * @return string|null
     */
    public function getAttachmentHash()
    {
        return $this->attachmentHash;
    }

    public function setAttachmentHash(?string $hash = null): self
    {
        $this->attachmentHash = $hash;

        return $this;
    }

    public function setMessage($message, $format = true)
    {
        $options = \XF::options();
        $disableEmbedsInUserReports = $this->disableEmbedsInUserReports;
        if ($disableEmbedsInUserReports)
        {
            $urlToRichPreview = $options->urlToRichPreview;
            $autoEmbedMedia = $options->autoEmbedMedia;
            $options->urlToPageTitle['enabled'] = false;
            $options->urlToRichPreview = false;
        }
        try
        {
            $ret = parent::setMessage($message, $format);
        }
        finally
        {
            if ($disableEmbedsInUserReports)
            {
                $options->autoEmbedMedia = $autoEmbedMedia;
                $options->urlToRichPreview = $urlToRichPreview;
            }
        }

        $this->comment->embed_metadata = $this->preparer->getEmbedMetadata();

        return $ret;
    }

    public function setDisableEmbedsInUserReports(bool $value): void
    {
        $this->disableEmbedsInUserReports = $value;
    }

    /**
     * @param bool $format
     * @return MessagePreparerService
     */
    protected function getMessagePreparer($format = true)
    {
        $this->preparer = parent::getMessagePreparer($format);

        return $this->preparer;
    }

    /**
     * Note; extended by other add-ons (SV/UserEssentials), do not change signature yet.
     * @return void
     */
    public function afterInsert()
    {
        if ($this->attachmentHash)
        {
            $this->associateAttachments($this->attachmentHash);
        }

        if ($this->logIp)
        {
            $ip = ($this->logIp === true ? \XF::app()->request()->getIp() : $this->logIp);
            $this->writeIpLog($ip);
        }
    }

    protected function triggerReindex(Entity $entity, string $field): void
    {
        $behaviors = $entity->getBehaviors();
        /** @var ?Indexable $behavior */
        $behavior = $behaviors['XF:Indexable'] ?? null;
        if ($behavior !== null)
        {
            $checkForUpdates = $behavior->getConfig('checkForUpdates') ?? [];
            if (in_array($field, $checkForUpdates, true))
            {
                $behavior->triggerReindex();
            }
        }
        /** @var ?IndexableContainer $behavior */
        $behavior = $behaviors['XF:IndexableContainer'] ?? null;
        if ($behavior !== null)
        {
            $checkForUpdates = $behavior->getConfig('checkForUpdates') ?? [];
            if (in_array($field, $checkForUpdates, true))
            {
                $behavior->triggerReindex();
            }
        }
    }

    protected function isCountedAsComment(): bool
    {
        return strlen($this->comment->message) !== 0
               || $this->comment->WarningLog !== null
            ;
    }

    public function afterCommentInsert(): void
    {
        $this->afterInsert();

        if ($this->isCountedAsComment())
        {
            $report = $this->comment->Report;
            // XF only considers having a message as a comment, so ensure the comment count is updated as expected
            if (strlen($this->comment->message) === 0)
            {
                $report->fastUpdate('comment_count', $report->comment_count + 1);
            }

            // the comment_count is updated in Commenter::_save() via fast_update
            // This is required for 'new report' search links, as otherwise the replies value isn't updated
            $this->triggerReindex($report, 'comment_count');
        }
    }

    public function afterReportInsert(): void
    {
        $this->afterInsert();
        // the report_count is updated via fast_update in Commenter::_save()
        $this->triggerReindex($this->comment->Report, 'report_count');
    }

    public function afterUpdate()
    {
        if ($this->attachmentHash)
        {
            $this->associateAttachments($this->attachmentHash);
        }
    }

    protected function associateAttachments(string $hash)
    {
        /** @var ExtendedReportCommentEntity $reportComment */
        $reportComment = $this->getComment();

        $associated = $this->getAttachmentPreparerSvc()->associateAttachmentsWithContent(
            $hash,
            'report_comment',
            $reportComment->report_comment_id
        );
        if ($associated)
        {
            $reportComment->fastUpdate('attach_count', $reportComment->attach_count + $associated);
            $this->triggerReindex($this->comment, 'attach_count');
        }
    }

    protected function writeIpLog($ip)
    {
        /** @var ExtendedReportCommentEntity $reportComment */
        $reportComment = $this->getComment();

        /** @var IpRepo $ipRepo */
        $ipRepo = Helper::repository(IpRepo::class);
        $ipEnt = $ipRepo->logIp($reportComment->user_id, $ip, 'report_comment', $reportComment->report_comment_id);
        if ($ipEnt)
        {
            $reportComment->fastUpdate('ip_id', $ipEnt->ip_id);
        }
    }

    protected function getAttachmentPreparerSvc(): AttachmentPreparerSvc
    {
        return Helper::service(AttachmentPreparerSvc::class);
    }
}