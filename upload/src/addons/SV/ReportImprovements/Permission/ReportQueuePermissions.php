<?php

namespace SV\ReportImprovements\Permission;

use SV\ReportCentreEssentials\Entity\ReportQueue as ReportQueueEntity;
use SV\ReportImprovements\Repository\ReportQueue as ReportQueueRepo;
use XF\Entity\Permission;
use XF\Entity\PermissionCombination;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Permission\FlatContentPermissions;
use XF\Phrase;
use function assert;

class ReportQueuePermissions extends FlatContentPermissions
{
    protected $privatePermissionGroupId = 'general';
    protected $privatePermissionId = 'viewReports';

    protected function getContentType(): string
    {
        return 'report_queue';
    }

    public function getAnalysisTypeTitle(): Phrase
    {
        return \XF::phrase('svReportImprovements_report_queue_permissions');
    }

    public function getContentList(): AbstractCollection
    {
        /** @var ReportQueueRepo $entryRepo */
        $entryRepo = $this->builder->em()->getRepository('SV\ReportImprovements:ReportQueue');
        return $entryRepo->getReportQueueList();
    }

    public function getContentTitle(Entity $entity): string
    {
        /** @var ReportQueueEntity $entity */
        return $entity->queue_name;
    }

    public function rebuildCombination(PermissionCombination $combination, array $basePerms)
    {
        // being notified on permission changes is surprisingly challenging
        $reportQueueRepo = \SV\StandardLib\Helper::repository(\SV\ReportImprovements\Repository\ReportQueue::class);
        assert($reportQueueRepo instanceof ReportQueueRepo);
        $reportQueueRepo->resetNonModeratorsWhoCanHandleReportCacheLater();

        parent::rebuildCombination($combination, $basePerms);
    }

    public function isValidPermission(Permission $permission): bool
    {
        return $permission->permission_group_id === 'report_queue' ||
               ($permission->permission_group_id === $this->privatePermissionGroupId && $permission->permission_id === $this->privatePermissionId);
    }

    protected function getFinalPerms($contentId, array $calculated, array &$childPerms): array
    {
        if (!isset($calculated['report_queue']))
        {
            $calculated['report_queue'] = [];
        }

        $final = $this->builder->finalizePermissionValues($calculated['report_queue']);
        if ($this->privatePermissionGroupId !== 'report_queue')
        {
            $final = $final + $this->builder->finalizePermissionValues($calculated[$this->privatePermissionGroupId]);
        }

        if (empty($final[$this->privatePermissionId]))
        {
            $childPerms[$this->privatePermissionGroupId][$this->privatePermissionId] = 'deny';
            $final = [];
        }

        return $final;
    }

    protected function getFinalAnalysisPerms($contentId, array $calculated, array &$childPerms): array
    {
        $final = $this->builder->finalizePermissionValues($calculated);

        if (empty($final[$this->privatePermissionGroupId][$this->privatePermissionId]))
        {
            $childPerms[$this->privatePermissionGroupId][$this->privatePermissionId] = 'deny';
        }

        return $final;
    }
}