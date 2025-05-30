<?php

namespace SV\ReportImprovements\XF\Service\Report;

use SV\ReportImprovements\Globals;
use SV\ReportImprovements\XF\Entity\Report as ExtendedReportEntity;
use SV\ReportImprovements\XF\Entity\ReportComment as ExtendedReportCommentEntity;
use SV\ReportImprovements\XF\Repository\Report as ExtendedReportRepo;
use SV\StandardLib\Helper;
use XF\App;
use XF\Entity\Report as ReportEntity;
use XF\Entity\ReportComment as ReportCommentEntity;
use XF\Entity\User as UserEntity;
use XF\Repository\Report as ReportRepo;
use XF\Repository\UserAlert as UserAlertRepo;
use function array_fill_keys;
use function array_keys;
use function array_unique;

/**
 * @extends \XF\Service\Report\Notifier
 * @property ExtendedReportEntity        $report
 * @property ExtendedReportCommentEntity $comment
 */
class Notifier extends XFCP_Notifier
{
    public function __construct(App $app, ReportEntity $report, ReportCommentEntity $comment)
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
        $this->notifyCommenterUserIds = array_unique($commenterUserIds);
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

        /** @var ExtendedReportRepo $reportRepo */
        $reportRepo = Helper::repository(ReportRepo::class);
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
            $usersWhoHaveAlreadyAlertedOnce = array_keys(\XF::db()->fetchAllKeyed('
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
            $usersWhoHaveAlreadyAlertedOnce = array_keys(\XF::db()->fetchAllKeyed('
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

        $userIds = array_fill_keys($userIds, true);
        foreach($usersWhoHaveAlreadyAlertedOnce as $userId)
        {
            unset($userIds[$userId]);
        }

        if (!$userIds)
        {
            return [];
        }

        $userIds = array_keys($userIds);
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
            $users = $users + Helper::findByIds(UserEntity::class, $toLoad, ['Profile', 'Option', 'PermissionCombination'])->toArray();
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
            /** @var UserAlertRepo $alertRepo */
            $alertRepo = Helper::repository(UserAlertRepo::class);
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