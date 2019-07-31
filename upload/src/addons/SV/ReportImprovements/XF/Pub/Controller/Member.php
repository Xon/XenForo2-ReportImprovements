<?php

namespace SV\ReportImprovements\XF\Pub\Controller;


use SV\ReportImprovements\Globals;
use XF\Mvc\ParameterBag;

/**
 * Extends \XF\Pub\Controller\Member
 */
class Member extends XFCP_Member
{
    public function actionRecentContent(ParameterBag $params)
    {
        if (isset(\XF::options()->svReportInAccountPostings))
        {
            Globals::$reportInAccountPostings = \XF::options()->svReportInAccountPostings;
        }
        try
        {
            return parent::actionRecentContent($params);
        }
        finally
        {
            Globals::$reportInAccountPostings = true;
        }
    }
}