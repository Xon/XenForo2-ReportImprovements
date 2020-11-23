<?php

namespace SV\ReportImprovements\XF\Service\User;

use XF\Mvc\Entity\Entity;

/**
 * Class Warn
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

    public function setResolveReport(bool $resolveReport)
    {
        $this->warning->setOption('svResolveReport', $resolveReport);
    }

    public function setupReplyBan(bool $sendAlert, string $reason, int $banLengthValue, string $banLengthUnit, bool $resolveReport = false)
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

        $this->replyBanSvc = $this->service('XF:Thread\ReplyBan', $post->Thread, $this->user);
        $this->replyBanSvc->setExpiryDate($banLengthUnit, $banLengthValue);
        $this->replyBanSvc->setPost($post);
        $this->replyBanSvc->setSendAlert($sendAlert);
        $this->replyBanSvc->setReason($reason);
        $this->replyBanSvc->getReplyBan()->setOption('svResolveReport', $resolveReport);
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
        if ($this->replyBanSvc)
        {
            /** @var \SV\ReportImprovements\XF\Entity\Warning $warning */
            $warning = $this->warning;
            // ensure the reply-ban is saved transactionally
            $warning->setSvReplyBan($this->replyBanSvc->getReplyBan());
        }

        $warning = parent::_save();

        if ($warning && $this->replyBanSvc)
        {
            // this doesn't save the entity, but sends notifications
            $this->replyBanSvc->save();
        }

        return $warning;
    }
}