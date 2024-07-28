<?php

namespace SV\ReportImprovements\XF\Service\Report;

use SV\ReportImprovements\Globals;
use SV\ReportImprovements\XF\Entity\Report as ExtendedReportEntity;
use SV\ReportImprovements\XF\Entity\ReportComment as ExtendedReportCommentEntity;
use SV\ReportImprovements\XF\Repository\Report;
use XF\Entity\ReportComment as ReportCommentEntity;
use XF\Entity\User;

/**
 * Class Commenter
 * @extends \XF\Service\Report\Commenter
 *
 * @package SV\ReportImprovements\XF\Service\Report
 * @property ExtendedReportEntity        $report
 * @property ExtendedReportCommentEntity $comment
 * @property CommentPreparer             $commentPreparer
 */
class Commenter extends XFCP_Commenter
{
    public function logIp(bool $logIp)
    {
        $this->commentPreparer->logIp($logIp);
    }

    /**
     * @return string|null
     */
    public function getAttachmentHash()
    {
        return $this->commentPreparer->getAttachmentHash();
    }

    public function setAttachmentHash(?string $hash = null): self
    {
        $this->commentPreparer->setAttachmentHash($hash);

        return $this;
    }

    public function isSendAlert(): bool
    {
        return $this->sendAlert;
    }

    public function getAlertComment(): string
    {
        return $this->alertComment;
    }

    protected function setCommentDefaults()
    {
        $report = $this->report;
        $wasSet = $report->getOption('svFakeOpen');
        if (Globals::$suppressReportStateChange && !$wasSet)
        {
            $report->setOption('svFakeOpen', true);
            try
            {
                parent::setCommentDefaults();
            }
            finally
            {
                $report->resetOption('svFakeOpen');
            }
        }
        else
        {
            parent::setCommentDefaults();
        }

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
     * @param User|null $assignedUser
     */
    public function setReportState($newState = null, ?User $assignedUser = null)
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
            if ($assignedUser === null)
            {
                $this->comment->assigned_user_id = null;
                $this->comment->assigned_username = '';
            }
            else
            {
                $this->comment->assigned_user_id = $assignedUser->user_id;
                $this->comment->assigned_username = $assignedUser->username;
            }
            // tracks unassignments, not just assignments
            $this->report->assigned_date = \XF::$time;
            $this->report->assigner_user_id = \XF::visitor()->user_id;
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

    protected function _save() : ReportCommentEntity
    {
        $db = $this->db();
        $db->beginTransaction();

        $content = parent::_save();

        $this->commentPreparer->afterCommentInsert();

        $db->commit();

        return $content;
    }

    /**
     * @throws \Exception
     */
    public function sendNotifications()
    {
        /** @var Report $reportRepo */
        $reportRepo = \SV\StandardLib\Helper::repository(\XF\Repository\Report::class);
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
}