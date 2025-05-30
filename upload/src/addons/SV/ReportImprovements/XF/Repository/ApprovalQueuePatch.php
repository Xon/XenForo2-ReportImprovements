<?php

namespace SV\ReportImprovements\XF\Repository;

use SV\ReportImprovements\XF\Finder\ApprovalQueue as ApprovalQueueFinder;

/**
 * @extends \XF\Repository\ApprovalQueue
 */
class ApprovalQueuePatch extends XFCP_ApprovalQueuePatch
{
    public function findUnapprovedContent()
    {
        $finder = parent::findUnapprovedContent();

        /** @var ApprovalQueueFinder $finder */
        $finder->svUndoSimpleReportJoin();

        return $finder;
    }
}