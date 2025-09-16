<?php

namespace SV\ReportImprovements\XF\Repository;

use SV\ReportImprovements\Repository\ReportQueue as ReportQueueRepo;
use SV\StandardLib\Helper;

/**
 * @extends \XF\Repository\PermissionCombination
 */
class PermissionCombination extends XFCP_PermissionCombination
{
    public function deleteUnusedPermissionCombinations()
    {
        $reportQueueRepo = Helper::repository(ReportQueueRepo::class);
        $reportQueueRepo->resetNonModeratorsWhoCanHandleReportCacheLater();

        return parent::deleteUnusedPermissionCombinations();
    }
}