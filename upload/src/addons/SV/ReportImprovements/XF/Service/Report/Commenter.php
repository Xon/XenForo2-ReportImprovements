<?php

namespace SV\ReportImprovements\XF\Service\Report;

use SV\ReportImprovements\Globals;
use SV\ReportImprovements\XF\Entity\Report as ExtendedReportEntity;
use SV\ReportImprovements\XF\Entity\ReportComment as ExtendedReportCommentEntity;
use XF\Entity\ReportComment as ReportCommentEntity;
use XF\Service\AbstractService;
use XF\Service\Attachment\Preparer as AttachmentPreparerSvc;

/**
 * Class Commenter
 * Extends \XF\Service\Report\Commenter
 *
 * @package SV\ReportImprovements\XF\Service\Report
 * @property ExtendedReportEntity        $report
 * @property ExtendedReportCommentEntity $comment
 */
class Commenter extends XFCP_Commenter
{
    /**
     * @var string|null
     */
    protected $attachmentHash;

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

    protected function setCommentDefaults()
    {
        parent::setCommentDefaults();
        $report = $this->report;
        $report->last_modified_date = \XF::$time;
        if ($report->last_modified_date < $report->getPreviousValue('last_modified_date'))
        {
            $report->last_modified_date = $report->getPreviousValue('last_modified_date');
            $report->last_modified_user_id = $report->getPreviousValue('last_modified_user_id');
            $report->last_modified_username = $report->getPreviousValue('last_modified_username');
        }
        else
        {
            $report->hydrateRelation('LastModified', $this->comment);
        }
    }

    /**
     * @param null                 $newState
     * @param \XF\Entity\User|null $assignedUser
     */
    public function setReportState($newState = null, \XF\Entity\User $assignedUser = null)
    {
        if (Globals::$suppressReportStateChange)
        {
            return;
        }

        $oldAssignedUserId = null;
        if ($newState !== 'open')
        {
            $oldAssignedUserId = $this->report->assigned_user_id;
        }

        parent::setReportState($newState, $assignedUser);

        if ($oldAssignedUserId !== null && $this->report->assigned_user_id === 0)
        {
            $oldState = $this->report->getExistingValue('report_state');
            $this->report->assigned_user_id = $oldAssignedUserId;
            if ($newState && $newState === $oldState)
            {
                $this->comment->state_change = '';
            }
        }

        if ($this->report->isChanged('assigned_user_id'))
        {
            $this->comment->assigned_user_id = $assignedUser ? $assignedUser->user_id : null;
            $this->comment->assigned_username = $assignedUser ? $assignedUser->username : '';
        }
    }

    protected function finalSetup()
    {
        $comment = $this->comment;
        $sendAlert = $this->sendAlert;
        $this->sendAlert = false;

        if ($sendAlert && $comment->isClosureComment())
        {
            $comment->alertSent = true;
            $comment->alertComment = $this->alertComment;
        }

        parent::finalSetup();

        $this->sendAlert = $sendAlert;
    }

    protected function postSave()
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

    protected function _save() : ReportCommentEntity
    {
        $db = $this->db();
        $db->beginTransaction();

        $content = parent::_save();

        $this->postSave();

        $db->commit();

        return $content;
    }

    /**
     * @throws \Exception
     */
    public function sendNotifications()
    {
        /** @var \SV\ReportImprovements\XF\Repository\Report $reportRepo */
        $reportRepo = $this->repository('XF:Report');
        Globals::$notifyReportUserIds = $reportRepo->findUserIdsToAlertForSvReportImprov($this->comment);
        try
        {
            parent::sendNotifications();
        }
        finally
        {
            Globals::$notifyReportUserIds = null;
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