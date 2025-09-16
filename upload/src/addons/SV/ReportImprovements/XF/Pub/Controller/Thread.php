<?php

namespace SV\ReportImprovements\XF\Pub\Controller;

use SV\ReportImprovements\Globals;
use SV\ReportImprovements\XF\ControllerPlugin\Warn as ExtendedWarnPlugin;
use SV\ReportImprovements\XF\Entity\ThreadReplyBan as ExtendedThreadReplyBanEntity;
use SV\ReportImprovements\XF\Service\Thread\ReplyBan as ExtendedReplyBanService;
use SV\StandardLib\Helper;
use XF\ControllerPlugin\Warn as WarnPlugin;
use XF\Entity\Thread as ThreadEntity;
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

    protected function setupThreadReplyBan(ThreadEntity $thread)
    {
        /** @var ExtendedReplyBanService $replyBanSrv */
        $replyBanSrv = parent::setupThreadReplyBan($thread);
        if (!$replyBanSrv)
        {
            return $replyBanSrv;
        }

        $replyBan = $replyBanSrv->getReplyBan();

        /** @var ExtendedWarnPlugin $warnPlugin */
        $warnPlugin = Helper::plugin($this, WarnPlugin::class);
        $warnPlugin->resolveReportFor($replyBan);

        return $replyBanSrv;
    }
}