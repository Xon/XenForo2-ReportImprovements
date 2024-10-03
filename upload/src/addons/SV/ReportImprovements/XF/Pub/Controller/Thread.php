<?php

namespace SV\ReportImprovements\XF\Pub\Controller;

use SV\ReportImprovements\Globals;
use SV\ReportImprovements\XF\ControllerPlugin\Warn as WarnPlugin;
use SV\ReportImprovements\XF\Entity\ThreadReplyBan as ExtendedThreadReplyBanEntity;
use SV\ReportImprovements\XF\Service\Thread\ReplyBan as ExtendedReplyBanService;
use SV\StandardLib\Helper;
use XF\ControllerPlugin\Warn;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\View as ViewReply;

/**
 * @extends \XF\Pub\Controller\Thread
 */
class Thread extends XFCP_Thread
{

    public function actionReplyBans(ParameterBag $params)
    {
        Globals::$resolveReplyBanOnDelete = $this->request()->exists('resolve_report') &&
                                            $this->filter('resolve_report', 'bool');
        try
        {
            $reply = parent::actionReplyBans($params);
        }
        finally
        {
            Globals::$resolveReplyBanOnDelete = false;
        }

        if ($reply instanceof ViewReply)
        {
            $canResolveReports = false;
            $replyBans = $reply->getParam('bans');
            if ($replyBans instanceof AbstractCollection)
            {
                /** @var ExtendedThreadReplyBanEntity $replyBan */
                foreach ($replyBans as $replyBan)
                {
                    if ($replyBan->canResolveLinkedReport())
                    {
                        $canResolveReports = true;
                        break;
                    }
                }
            }

            $reply->setParam('canResolveReports', $canResolveReports);
        }

        return $reply;
    }

    protected function setupThreadReplyBan(\XF\Entity\Thread $thread)
    {
        /** @var ExtendedReplyBanService $replyBanSrv */
        $replyBanSrv = parent::setupThreadReplyBan($thread);
        if (!$replyBanSrv)
        {
            return $replyBanSrv;
        }

        $replyBan = $replyBanSrv->getReplyBan();

        /** @var WarnPlugin $warnPlugin */
        $warnPlugin = Helper::plugin($this, Warn::class);
        $warnPlugin->resolveReportFor($replyBan);

        return $replyBanSrv;
    }
}