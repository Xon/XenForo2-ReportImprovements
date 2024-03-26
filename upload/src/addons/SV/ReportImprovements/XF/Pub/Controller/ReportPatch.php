<?php

namespace SV\ReportImprovements\XF\Pub\Controller;

use SV\ReportImprovements\Globals;
use SV\ReportImprovements\XF\Entity\Report as ExtendedReportEntity;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\View as ViewReply;

/**
 * Class Report
 * @extends \XF\Pub\Controller\Report
 *
 * @package SV\ReportImprovements\XF\Pub\Controller
 */
class ReportPatch extends XFCP_ReportPatch
{
    public function actionView(ParameterBag $params)
    {
        Globals::$shimCommentsFinder = true;
        try
        {
            $reply = parent::actionView($params);
        }
        finally
        {
            Globals::$shimCommentsFinder = false;
        }

        if ($reply instanceof ViewReply &&
            ($report = $reply->getParam('report')))
        {
            /** @var ExtendedReportEntity $report */
            // XF uses `$report->getRelationFinder('Comments')` but with a hard-set order
            // Extend getRelationFinder to add an impossible where, and then fetch the correct comments in the correct order now
            $reply->setParam('comments', $report->Comments);
        }

        return $reply;
    }
}
