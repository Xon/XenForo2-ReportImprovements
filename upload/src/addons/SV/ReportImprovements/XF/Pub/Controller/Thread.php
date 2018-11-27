<?php

namespace SV\ReportImprovements\XF\Pub\Controller;

use SV\ReportImprovements\Globals;
use XF\Mvc\ParameterBag;

/**
 * Class Thread
 * 
 * Extends \XF\Pub\Controller\Thread
 *
 * @package SV\ReportImprovements\XF\Pub\Controller
 */
class Thread extends XFCP_Thread
{
    /**
     * @param ParameterBag $params
     *
     * @return \XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
     */
    public function actionReplyBans(ParameterBag $params)
    {
        if ($this->isPost())
        {
            Globals::$resolveThreadReplyBanReport = $this->filter('resolve_report', 'bool');
        }

        try
        {
            return parent::actionReplyBans($params);
        }
        finally
        {
            Globals::$resolveThreadReplyBanReport = null;
        }
    }
}