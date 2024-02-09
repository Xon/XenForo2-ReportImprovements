<?php

namespace SV\ReportImprovements\XF\Repository;

use SV\ReportImprovements\Repository\ReportQueue as ReportQueueRepo;
use function assert;

/**
 * @extends \XF\Repository\PermissionCombination
 */
class PermissionCombination extends XFCP_PermissionCombination
{
    public function deleteUnusedPermissionCombinations()
    {
        $reportQueueRepo = \XF::repository('SV\ReportImprovements:ReportQueue');
        assert($reportQueueRepo instanceof ReportQueueRepo);
        $reportQueueRepo->resetNonModeratorsWhoCanHandleReportCacheLater();

        return parent::deleteUnusedPermissionCombinations();
    }
}