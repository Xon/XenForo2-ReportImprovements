<?php

namespace SV\ReportImprovements\XF\Service\User;

use SV\ReportImprovements\Globals;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;

/**
 * Class Warn
 * 
 * Extends \XF\Service\User\Warn
 *
 * @package SV\ReportImprovements\XF\Service\User
 */
class Warn extends XFCP_Warn
{
    /**
     * @var \XF\Service\Thread\ReplyBan|\SV\ReportImprovements\XF\Service\Thread\ReplyBan
     */
    protected $replyBanSvc;

    /**
     * @param      $sendAlert
     * @param      $reason
     * @param null $resolveReport
     */
    public function setupReplyBan($sendAlert, $reason, $resolveReport = null)
    {
        if (!$this->content instanceof \XF\Entity\Post)
        {
            throw new \LogicException('Content must be instance of post.');
        }

        $post = $this->content;
        if (!$post->Thread)
        {
            throw new \LogicException('Post does not have a valid thread.');
        }
        $thread = $post->Thread;

        if ($resolveReport)
        {
            Globals::$resolveWarningReport = false;
            Globals::$resolveThreadReplyBanReport = true;
        }

        $this->replyBanSvc = $this->service('XF:Thread\ReplyBan', $post->Thread, $this->user);
        $this->replyBanSvc->setPostIdForWarning($post->post_id, $thread->title);
        $this->replyBanSvc->setSendAlert($sendAlert);
        $this->replyBanSvc->setReason($reason);
    }

    /**
     * @return array
     */
    protected function _validate()
    {
        $warningErrors = parent::_validate();

        if ($this->replyBanSvc && !$this->replyBanSvc->validate($replyBanSvcErrors))
        {
            $warningErrors += $replyBanSvcErrors;
        }

        return $warningErrors;
    }

    /**
     * @return \XF\Entity\Warning|Entity
     */
    protected function _save()
    {
        $warning = parent::_save();

        if ($warning && $this->replyBanSvc)
        {
            $this->replyBanSvc->save();
        }

        return $warning;
    }
}