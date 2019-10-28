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

        if ($this->request()->exists('resolve_report') &&
            $this->filter('resolve_report', 'bool'))
        {
            // TODO: fix me; racy
            /** @var \SV\ReportImprovements\XF\Entity\Report $report */
            $report = $this->finder('XF:Report')
                           ->where('content_type', 'user')
                           ->where('content_id', $replyBanSrv->getUser()->user_id)
                           ->fetchOne();
            $resolveWarningReport = !$report || $report->canView() && $report->canUpdate($error);
        }
        else
        {
            $resolveWarningReport = false;
        }

        $replyBanSrv->getReplyBan()->setOption('svResolveReport',  $resolveWarningReport);


        return $replyBanSrv;
    }
}