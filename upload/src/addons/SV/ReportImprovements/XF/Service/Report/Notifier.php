<?php

namespace SV\ReportImprovements\XF\Service\Report;

use SV\ReportImprovements\Globals;
use SV\StandardLib\Helper;
use XF\App;
use XF\Entity\Report;
use XF\Entity\ReportComment;
use XF\Entity\User as UserEntity;
use XF\Repository\UserAlert;

/**
 * Class Notifier
 * @extends \XF\Service\Report\Notifier
 *
 * @package SV\ReportImprovements\XF\Service\Report
 * @property \SV\ReportImprovements\XF\Entity\Report        $report
 * @property \SV\ReportImprovements\XF\Entity\ReportComment $comment
 */
class Notifier extends XFCP_Notifier
{
    public function __construct(App $app, Report $report, ReportComment $comment)
    {
        parent::__construct($app, $report, $comment);
        if (Globals::$notifyReportUserIds)
        {
            $this->setCommentersUserIds(Globals::$notifyReportUserIds);
        }
    }

    /**
     * @var array
     */
    protected $notifyCommenterUserIds = [];

    /**
     * @return array
     */
    public function getNotifyCommenterUserIds()
    {
        return $this->notifyCommenterUserIds;
    }

    /**
     * @param array $commenterUserIds
     */
    public function setCommentersUserIds(array $commenterUserIds)
    {
        $this->notifyCommenterUserIds = \array_unique($commenterUserIds);
    }

    /**
     * XF2.2 compatibility
     * */
    public function notify()
    {
        /** @noinspection PhpUndefinedMethodInspection */
        parent::notify();

        $this->svNotifyCommenters();
    }

    public function notifyCreate()
    {
        parent::notifyCreate();

        /** @var \SV\ReportImprovements\XF\Repository\Report $reportRepo */
        $reportRepo = \SV\StandardLib\Helper::repository(\XF\Repository\Report::class);
        $userIdsToAlert = $reportRepo->findUserIdsToAlertForSvReportImprov($this->report);
        $this->setCommentersUserIds($userIdsToAlert);

        $this->svNotifyCommenters();
    }

    public function notifyMentioned()
    {
        parent::notifyMentioned();

        $this->svNotifyCommenters();
    }

    protected function svNotifyCommenters()
    {
        if (!$this->report->exists() ||
            !$this->comment->exists())
        {
            return;
        }

        $commenterUsers = $this->getNotifyCommenterUserIds();
        if (!$commenterUsers)
        {
            return;
        }
        $notifiableUsers = $this->getUsersForCommentInsertNotification();

        foreach ($commenterUsers AS $userId)
        {
            if (isset($notifiableUsers[$userId]))
            {
                $user = $notifiableUsers[$userId];
                if (\XF::asVisitor($user, function () { return $this->comment->canView(); }))
                {
                    $this->sendCommentNotification($user);
                }
            }
        }
        $this->notifyCommenterUserIds = [];
    }

    protected function getUsersForCommentInsertNotification(): array
    {
        $userIds = $this->getNotifyCommenterUserIds();
        if (!$userIds)
        {
            return [];
        }

        $addOns = \XF::app()->container('addon.cache');
        if (($addOns['SV/PersistentAlerts'] ?? 0) >= 2030000)
        {
            $usersWhoHaveAlreadyAlertedOnce = \array_keys($this->db()->fetchAllKeyed('
                SELECT alerted_user_id
                FROM xf_user_alert
                WHERE view_date = 0
                  AND content_type = ?
                  AND sv_container_id = ?
                  AND action = ?
            ', 'alerted_user_id', ['report_comment', $this->comment->report_id, 'insert']));
        }
        else
        {
            $usersWhoHaveAlreadyAlertedOnce = \array_keys($this->db()->fetchAllKeyed('
                SELECT user_alert.alerted_user_id
                FROM xf_user_alert AS user_alert
                INNER JOIN xf_report_comment AS report_comment
                  ON (report_comment.report_comment_id = user_alert.content_id)
                WHERE user_alert.view_date = 0
                  AND user_alert.content_type = ?
                  AND report_comment.report_id = ?
                  AND user_alert.action = ?
            ', 'alerted_user_id', ['report_comment', $this->comment->report_id, 'insert']));
        }

        $userIds = \array_fill_keys($userIds, true);
        foreach($usersWhoHaveAlreadyAlertedOnce as $userId)
        {
            unset($userIds[$userId]);
        }

        if (!$userIds)
        {
            return [];
        }

        $userIds = \array_keys($userIds);
        $toLoad = [];
        $users = [];
        foreach($userIds as $userId)
        {
            $user = Helper::findCached(UserEntity::class, $userId);
            if ($user !== null)
            {
                $users[$userId] = $user;
            }
            else
            {
                $toLoad[] = $userId;
            }
        }

        if ($toLoad)
        {
            $users = $users + \SV\StandardLib\Helper::findByIds(\XF\Entity\User::class, $toLoad, ['Profile', 'Option', 'PermissionCombination'])->toArray();
        }

        return $users;
    }

    /**
     * @param UserEntity $user
     * @return bool
     */
    protected function sendCommentNotification(UserEntity $user)
    {
        $comment = $this->comment;
        $commentUserId = $comment->user_id;
        $userId = $user->user_id;

        if (empty($this->usersAlerted[$userId]) && ($userId !== $commentUserId))
        {
            /** @var UserAlert $alertRepo */
            $alertRepo = \SV\StandardLib\Helper::repository(\XF\Repository\UserAlert::class);
            if ($alertRepo->alert($user, $commentUserId, $comment->username, 'report_comment', $comment->report_comment_id, 'insert', [
                'depends_on_addon_id' => 'SV/ReportImprovements'
            ]))
            {
                $this->usersAlerted[$userId] = true;

                return true;
            }
        }

        return false;
    }
}