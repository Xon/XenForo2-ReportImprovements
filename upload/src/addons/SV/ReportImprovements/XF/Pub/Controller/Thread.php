<?php

namespace SV\ReportImprovements\XF\Pub\Controller;

use SV\ReportImprovements\Globals;
use XF\Mvc\ParameterBag;

/**
 * Class Thread
 * Extends \XF\Pub\Controller\Thread
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
        /** @var \SV\ReportImprovements\XF\Service\Thread\ReplyBan $replyBanSrv */
        $replyBanSrv = parent::setupThreadReplyBan($thread);
        if (!$replyBanSrv)
        {
            return $replyBanSrv;
        }

        $replyBan = $replyBanSrv->getReplyBan();

        /** @var \SV\ReportImprovements\XF\ControllerPlugin\Warn $warnPlugin */
        $warnPlugin = $this->plugin('XF:Warn');
        $warnPlugin->resolveReportFor($replyBan, null, function() use ($replyBan) {
            // TODO: fix me; racy
            return $this->finder('XF:Report')
                        ->where('content_type', 'user')
                        ->where('content_id', $replyBan->user_id)
                        ->fetchOne();
        });

        return $replyBanSrv;
    }
}