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
        $visitor = \XF::visitor();
        $visitor->setOption('reportSearch', (bool)(\XF::options()->svReportInAccountPostings ?? false));
        try
        {
            return parent::actionRecentContent($params);
        }
        finally
        {
            $visitor->resetOption('reportSearch');
        }
    }
}