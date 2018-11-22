<?php

namespace SV\ReportImprovements\XF\Service\Report;

use SV\ReportImprovements\Globals;

/**
 * Class Notifier
 * 
 * Extends \XF\Service\Report\Notifier
 *
 * @package SV\ReportImprovements\XF\Service\Report
 *
 * @property \SV\ReportImprovements\XF\Entity\Report $report
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

        $notifiableUsers = $this->getUsersForCommentInsertNotification();
        $commenterUsers = $this->getNotifyCommenterUserIds();

        foreach ($commenterUsers AS $k => $userId)
        {
            if (isset($notifiableUsers[$userId]))
            {
                $user = $notifiableUsers[$userId];
                if (\XF::asVisitor($user, function() { return $this->report->canView(); }))
                {
                    $this->sendCommentNotification($user);
                }
            }
            unset($commenterUsers[$k]);
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

        $users = $this->app->em()->findByIds('XF:User', $userIds, ['Profile', 'Option']);
        if (!$users->count())
        {
            return [];
        }

        $usersWhoHaveAlreadyAlertedOnce = array_keys($this->db()->fetchAllKeyed('
            SELECT alerted_user_id
            FROM xf_user_alert
            WHERE view_date = 0
              AND content_type = ?
              AND content_id = ?
              AND action = ?
        ', 'alerted_user_id', ['report', $this->comment->report_id, 'comment']));

        /**
         * @var int $userId
         * @var \SV\ReportImprovements\XF\Entity\User $user
         */
        foreach ($users AS $userId => $user)
        {
            if (!\XF::asVisitor($user, function(){ return $this->report->canView(); }))
            {
                unset($users[$userId]);
            }

            if (\in_array($user->user_id, $usersWhoHaveAlreadyAlertedOnce, true))
            {
                unset($users[$userId]);
            }
        }

        return $users;
    }

    /**
     * @param \XF\Entity\User $user
     *
     * @return bool
     */
    protected function sendCommentNotification(\XF\Entity\User $user)
    {
        $comment = $this->comment;

        if (empty($this->usersAlertedForInsert[$user->user_id]) && ($user->user_id !== $comment->user_id))
        {
            /** @var \XF\Repository\UserAlert $alertRepo */
            $alertRepo = $this->app->repository('XF:UserAlert');
            if ($alertRepo->alert($user, $comment->user_id, $comment->username, 'report', $comment->report_id, 'comment', [
                'comment' => $comment->toArray()
            ]))
            {
                $this->usersAlertedForInsert[$user->user_id] = true;
                return true;
            }
        }

        return false;
    }
}