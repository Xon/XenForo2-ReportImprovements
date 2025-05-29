<?php

namespace SV\ReportImprovements\XF\Repository;

use SV\ReportImprovements\XF\Finder\ApprovalQueue as ApprovalQueueFinder;

/**
 * @extends \XF\Repository\ApprovalQueue
 */
class ApprovalQueuePatch extends XFCP_ApprovalQueuePatch
{
    /** @noinspection PhpMissingReturnTypeInspection */
    public function findUnapprovedContent()
    {
        $finder = parent::findUnapprovedContent();

        /** @var ApprovalQueueFinder $unapprovedFinder */
        $finder->svUndoSimpleReportJoin();

        return $finder;
    }
}