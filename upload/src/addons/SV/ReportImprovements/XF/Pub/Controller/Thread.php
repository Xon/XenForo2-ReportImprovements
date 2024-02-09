<?php

namespace SV\ReportImprovements\XF\Pub\Controller;

use SV\ReportImprovements\Globals;
use SV\ReportImprovements\XF\ControllerPlugin\Warn as WarnPlugin;
use SV\ReportImprovements\XF\Service\Thread\ReplyBan;
use XF\Mvc\ParameterBag;

/**
 * Class Thread
 * @extends \XF\Pub\Controller\Thread
 *
 * @package SV\ReportImprovements\XF\Pub\Controller
 */
class Thread extends XFCP_Thread
{

    public function actionReplyBans(ParameterBag $params)
    {
        Globals::$resolveReplyBanOnDelete = $this->request()->exists('resolve_report') &&
                                            $this->filter('resolve_report', 'bool');
        try
        {
            return parent::actionReplyBans($params);
        }
        finally
        {
            Globals::$resolveReplyBanOnDelete = false;
        }
    }

    protected function setupThreadReplyBan(\XF\Entity\Thread $thread)
    {
        /** @var ReplyBan $replyBanSrv */
        $replyBanSrv = parent::setupThreadReplyBan($thread);
        if (!$replyBanSrv)
        {
            return $replyBanSrv;
        }

        $replyBan = $replyBanSrv->getReplyBan();

        /** @var WarnPlugin $warnPlugin */
        $warnPlugin = $this->plugin('XF:Warn');
        $warnPlugin->resolveReportFor($replyBan);

        return $replyBanSrv;
    }
}