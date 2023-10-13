<?php

namespace SV\ReportImprovements\XF\Entity;

use SV\ReportImprovements\Repository\ReportQueue as ReportQueueRepo;
use function assert;

/**
 * Extends \XF\Entity\PermissionEntry
 */
class PermissionEntry extends XFCP_PermissionEntry
{
    protected function _postSave()
    {
        parent::_postSave();
        if ($this->isInsert() || $this->isChanged('permission_value'))
        {
            $this->svInvalidateReportCache();
        }
    }

    protected function _postDelete()
    {
        parent::_postDelete();
        $this->svInvalidateReportCache();
    }

    protected function shouldInvalidateReportCache(): bool
    {
        return ($this->permission_group_id === 'general' && $this->permission_id === 'viewReports')
               || ($this->permission_group_id === 'report_queue' && $this->permission_id === 'view')
               || ($this->permission_group_id === 'report_queue' && $this->permission_id === 'updateReport');
    }

    protected function svInvalidateReportCache(): void
    {
        if ($this->shouldInvalidateReportCache())
        {
            $reportQueueRepo = \XF::repository('SV\ReportImprovements:ReportQueue');
            assert($reportQueueRepo instanceof ReportQueueRepo);
            $reportQueueRepo->resetNonModeratorsWhoCanHandleReportCacheLater();
        }
    }
}