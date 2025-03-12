<?php

namespace SV\ReportImprovements\XF\Pub\Controller;


use XF\Mvc\ParameterBag;

/**
 * @extends \XF\Pub\Controller\Member
 */
class Member extends XFCP_Member
{
    public function actionRecentContent(ParameterBag $params)
    {
        $visitor = \XF::visitor();
        $visitor->setOption('reportSearch', \XF::options()->svReportInAccountPostings ?? false);
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