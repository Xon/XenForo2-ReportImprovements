<?php

namespace SV\ReportImprovements\XF\Repository;

/**
 * Extends \XF\Repository\PermissionCombination
 */
class PermissionCombination extends XFCP_PermissionCombination
{
    public function deleteUnusedPermissionCombinations()
    {
        /** @var Report $repo */
        $repo = \XF::repository('XF:Report');
        $repo->deferResetNonModeratorsWhoCanHandleReportCache();

        return parent::deleteUnusedPermissionCombinations();
    }
}