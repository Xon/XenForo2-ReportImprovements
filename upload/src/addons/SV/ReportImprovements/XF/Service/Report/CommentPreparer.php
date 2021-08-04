<?php

namespace SV\ReportImprovements\XF\Service\Report;

use SV\ReportImprovements\XF\Entity\Report as ExtendedReportEntity;
use SV\ReportImprovements\XF\Entity\ReportComment as ExtendedReportCommentEntity;
use XF\Service\AbstractService;
use XF\Service\Attachment\Preparer as AttachmentPreparerSvc;

/**
 * Extends \XF\Service\Report\CommentPreparer
 *
 * @package SV\ReportImprovements\XF\Service\Report
 * @property ExtendedReportEntity        $report
 * @property ExtendedReportCommentEntity $comment
 */
class CommentPreparer extends XFCP_CommentPreparer
{
    /** @var \XF\Service\Message\Preparer */
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
     * @return \XF\Service\Message\Preparer
     */
    protected function getMessagePreparer($format = true)
    {
        $this->preparer = parent::getMessagePreparer($format);

        return $this->preparer;
    }

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
        }
    }

    protected function writeIpLog($ip)
    {
        /** @var ExtendedReportCommentEntity $reportComment */
        $reportComment = $this->getComment();

        /** @var \XF\Repository\IP $ipRepo */
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