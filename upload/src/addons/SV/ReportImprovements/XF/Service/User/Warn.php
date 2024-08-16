<?php

namespace SV\ReportImprovements\XF\Service\User;

use SV\ReportImprovements\Entity\IReportResolver;
use SV\ReportImprovements\XF\Service\Thread\ReplyBan as ExtendedReplyBanService;
use SV\StandardLib\Helper;
use XF\Entity\Post;
use XF\Entity\Warning;
use XF\Mvc\Entity\Entity;
use XF\PrintableException;
use XF\Service\Thread\ReplyBan as ReplyBanEntity;

/**
 * @extends \XF\Service\User\Warn
 *
 * @property \SV\ReportImprovements\XF\Entity\Warning $warning
 */
class Warn extends XFCP_Warn
{
    /**
     * @return \SV\ReportImprovements\XF\Entity\Warning|Warning|null
     */
    public function getWarning()
    {
        return $this->warning;
    }

    /**
     * @var ReplyBanEntity|ExtendedReplyBanService
     */
    protected $replyBanSvc;

    public function setResolveReport(bool $resolveReport, bool $alert, string $comment = '')
    {
        /** @var IReportResolver $entity */
        $entity = $this->replyBanSvc ? $this->replyBanSvc->getReplyBan() : null;
        if ($entity === null)
        {
            $entity = $this->warning;
        }

        if ($entity)
        {
            $entity->resolveReportFor($resolveReport, $alert, $comment);
        }
    }

    public function setupReplyBan(bool $sendAlert, string $reason, ?int $banLengthValue = null, ?string $banLengthUnit = null)
    {
        if (!$this->content instanceof Post)
        {
            throw new \LogicException('Content must be instance of post.');
        }

        $post = $this->content;
        if (!$post->Thread)
        {
            throw new \LogicException('Post does not have a valid thread.');
        }

        $this->replyBanSvc = Helper::service(ReplyBanEntity::class, $post->Thread, $this->user);
        $this->replyBanSvc->setExpiryDate($banLengthUnit, $banLengthValue);
        $this->replyBanSvc->setPost($post);
        $this->replyBanSvc->setSendAlert($sendAlert);
        $this->replyBanSvc->setReason($reason);
        // prevent the warning from re-opening a resolved report
        $this->warning->setOption('svCanReopenReport', false);
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
     * @return Warning|Entity
     * @throws PrintableException
     */
    protected function _save()
    {
        if ($this->contentAction === 'public')
        {
            $this->warning->setOption('svPublicBanner', $this->contentActionOptions['message'] ?? '');
        }

        if ($this->replyBanSvc)
        {
            // ensure the reply-ban is saved transactionally
            $this->warning->setSvReplyBan($this->replyBanSvc->getReplyBan());
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