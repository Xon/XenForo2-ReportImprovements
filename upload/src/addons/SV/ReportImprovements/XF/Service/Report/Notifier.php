<?php

namespace SV\ReportImprovements\XF\Service\Report;

/**
 * Class Notifier
 * Extends \XF\Service\Report\Notifier
 *
 * @package SV\ReportImprovements\XF\Service\Report
 * @property \SV\ReportImprovements\XF\Entity\Report        $report
 * @property \SV\ReportImprovements\XF\Entity\ReportComment $comment
 */
class Notifier extends XFCP_Notifier
{
    /**
     * @var array
     */
    protected $notifyCommenterUserIds = [];

    protected $usersAlertedForInsert = [];

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
     * @throws \Exception
     */
    public function notify()
    {
        parent::notify();

        $commenterUsers = $this->getNotifyCommenterUserIds();
        if (!$commenterUsers)
        {
            return;
        }
        $notifiableUsers = $this->getUsersForCommentInsertNotification();

        foreach ($commenterUsers AS $k => $userId)
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

    /**
     * @return array|\XF\Mvc\Entity\ArrayCollection
     * @throws \Exception
     */
    protected function getUsersForCommentInsertNotification()
    {
        $userIds = $this->getNotifyCommenterUserIds();
        if (!$userIds)
        {
            return [];
        }

        $usersWhoHaveAlreadyAlertedOnce = array_keys($this->db()->fetchAllKeyed('
            SELECT user_alert.alerted_user_id
            FROM xf_user_alert AS user_alert
            INNER JOIN xf_report_comment AS report_comment
              ON (report_comment.report_comment_id = user_alert.content_id)
            WHERE user_alert.view_date = 0
              AND user_alert.content_type = ?
              AND report_comment.report_id = ?
              AND user_alert.action = ?
        ', 'alerted_user_id', ['report_comment', $this->comment->report_comment_id, 'insert']));

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
        $em = $this->app->em();
        $toLoad = [];
        $users = [];
        foreach($userIds as $userId)
        {
            $user = $em->findCached('XF:User', $userId);
            if ($user)
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
            $users = $users + $this->app->em()->findByIds('XF:User', $toLoad, ['Profile', 'Option', 'PermissionCombination']);
        }

        return $users;
    }

    /**
     * @param \XF\Entity\User $user
     * @return bool
     */
    protected function sendCommentNotification(\XF\Entity\User $user)
    {
        $comment = $this->comment;

        if (empty($this->usersAlertedForInsert[$user->user_id]) && ($user->user_id !== $comment->user_id))
        {
            /** @var \XF\Repository\UserAlert $alertRepo */
            $alertRepo = $this->app->repository('XF:UserAlert');
            if ($alertRepo->alert($user, $comment->user_id, $comment->username, 'report_comment', $comment->report_comment_id, 'insert', [
                'report_id' => $comment->report_id,
            ]))
            {
                $this->usersAlertedForInsert[$user->user_id] = true;

                return true;
            }
        }

        return false;
    }
}