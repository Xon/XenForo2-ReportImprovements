<?php

namespace SV\ReportImprovements\XF\Entity;

use SV\ReportImprovements\Repository\ReportQueue as ReportQueueRepo;
use function assert;

/**
 * @extends \XF\Entity\PermissionEntryContent
 */
class PermissionEntryContent extends XFCP_PermissionEntryContent
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
            $reportQueueRepo = \SV\StandardLib\Helper::repository(\SV\ReportImprovements\Repository\ReportQueue::class);
            assert($reportQueueRepo instanceof ReportQueueRepo);
            $reportQueueRepo->resetNonModeratorsWhoCanHandleReportCacheLater();
        }
    }
}