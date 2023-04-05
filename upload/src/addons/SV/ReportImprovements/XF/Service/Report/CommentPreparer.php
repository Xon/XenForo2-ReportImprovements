<?php

namespace SV\ReportImprovements\XF\Service\Report;

use SV\ReportImprovements\XF\Entity\Report as ExtendedReportEntity;
use SV\ReportImprovements\XF\Entity\ReportComment as ExtendedReportCommentEntity;
use XF\Behavior\Indexable;
use XF\Behavior\IndexableContainer;
use XF\Mvc\Entity\Entity;
use XF\Repository\Ip;
use XF\Service\AbstractService;
use XF\Service\Attachment\Preparer as AttachmentPreparerSvc;
use XF\Service\Message\Preparer;
use function assert;
use function in_array;
use function strlen;

/**
 * Extends \XF\Service\Report\CommentPreparer
 *
 * @package SV\ReportImprovements\XF\Service\Report
 * @property ExtendedReportEntity        $report
 * @property ExtendedReportCommentEntity $comment
 */
class CommentPreparer extends XFCP_CommentPreparer
{
    /** @var Preparer */
    protected $preparer;

    /**  @var string|null  */
    protected $attachmentHash;

    /** @var bool */
    protected $logIp = false;

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

    public function setAttachmentHash(string $hash = null): self
    {
        $this->attachmentHash = $hash;

        return $this;
    }

    public function setMessage($message, $format = true)
    {
        $ret = parent::setMessage($message, $format);

        $this->comment->embed_metadata = $this->preparer->getEmbedMetadata();

        return $ret;
    }

    /**
     * @param bool $format
     *
     * @return Preparer
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
            $ip = ($this->logIp === true ? $this->app->request()->getIp() : $this->logIp);
            $this->writeIpLog($ip);
        }
    }

    protected function triggerReindex(Entity $entity, string $field): void
    {
        $behavior = $entity->getBehavior('XF:Indexable');
        if ($behavior !== null)
        {
            assert($behavior instanceof Indexable);
            $checkForUpdates = $behavior->getConfig('checkForUpdates') ?? [];
            if (in_array($field, $checkForUpdates, true))
            {
                $behavior->triggerReindex();
            }
        }
        $behavior = $entity->getBehavior('XF:IndexableContainer');
        if ($behavior !== null)
        {
            assert($behavior instanceof IndexableContainer);
            $checkForUpdates = $behavior->getConfig('checkForUpdates') ?? [];
            if (in_array($field, $checkForUpdates, true))
            {
                $behavior->triggerReindex();
            }
        }
    }

    public function afterCommentInsert(): void
    {
        $this->afterInsert();

        $countsAsComment = strlen($this->comment->message) !== 0 || $this->comment->WarningLog !== null;
        if ($countsAsComment)
        {
            $report = $this->comment->Report;
            // Adding a WarningLog entry is considered a comment, but in XF it isn't
            if (!$this->comment->is_report && $this->comment->WarningLog !== null)
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

        /** @var IP $ipRepo */
        $ipRepo = $this->repository('XF:Ip');
        $ipEnt = $ipRepo->logIp($reportComment->user_id, $ip, 'report_comment', $reportComment->report_comment_id);
        if ($ipEnt)
        {
            $reportComment->fastUpdate('ip_id', $ipEnt->ip_id);
        }
    }

    /**
     * @return AbstractService|AttachmentPreparerSvc
     * @noinspection PhpReturnDocTypeMismatchInspection
     */
    protected function getAttachmentPreparerSvc()
    {
        return $this->service('XF:Attachment\Preparer');
    }
}