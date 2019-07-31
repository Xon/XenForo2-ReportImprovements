<?php

namespace SV\ReportImprovements\XF\Service\Report;

use SV\ReportImprovements\Globals;
use SV\ReportImprovements\XF\Entity\Report;
use SV\ReportImprovements\XF\Entity\ReportComment;

/**
 * Class Commenter
 *
 * Extends \XF\Service\Report\Commenter
 *
 * @package SV\ReportImprovements\XF\Service\Report
 *
 * @property Report $report
 * @property ReportComment $comment
 */
class Commenter extends XFCP_Commenter
{
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
            $oldAssignedUserId = $this->report->assigned_user_id ;
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
            $this->comment->assigned_user_id = $assignedUser->user_id;
            $this->comment->assigned_username = $assignedUser->username;
        }
    }

    protected function finalSetup()
    {
        $sendAlert = $this->sendAlert;
        $this->sendAlert = false;

        if ($sendAlert)
        {
            $this->comment->alertSent = true;
            $this->comment->alertComment = $this->alertComment;
        }

        parent::finalSetup();

        $this->sendAlert = $sendAlert;
    }

    /**
     * @throws \Exception
     */
    public function sendNotifications()
    {
        parent::sendNotifications();

        $comment = $this->comment;

        /** @var \SV\ReportImprovements\XF\Repository\Report $reportRepo */
        $reportRepo = $this->repository('XF:Report');
        $userIdsToAlert = $reportRepo->findUserIdsToAlertForSvReportImprov($comment);

        /** @var \SV\ReportImprovements\XF\Service\Report\Notifier $notifier */
        $notifier = $this->service('XF:Report\Notifier', $this->report, $comment);
        $notifier->setCommentersUserIds($userIdsToAlert);
        $notifier->notify();
    }
}