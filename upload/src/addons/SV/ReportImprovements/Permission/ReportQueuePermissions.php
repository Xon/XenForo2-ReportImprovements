<?php

namespace SV\ReportImprovements\Permission;

use SV\ReportCentreEssentials\Entity\ReportQueue as ReportQueueEntity;
use XF\Mvc\Entity\Entity;

class ReportQueuePermissions extends \XF\Permission\FlatContentPermissions
{
    protected function getContentType()
    {
        return 'report_queue';
    }

    public function getAnalysisTypeTitle()
    {
        return \XF::phrase('svReportImprovements_report_queue_permissions');
    }

    public function getContentList()
    {
        $addonsCache = \XF::app()->container('addon.cache');
        if (isset($addonsCache['SV/ReportCentreEssentials']))
        {
            /** @var \SV\ReportCentreEssentials\Repository\ReportQueue $entryRepo */
            $entryRepo = $this->builder->em()->getRepository('SV\ReportCentreEssentials:ReportQueue');
            return $entryRepo->findReportQueues()->fetch();
        }

        /** @var \SV\ReportImprovements\Repository\ReportQueue $entryRepo */
        $entryRepo = $this->builder->em()->getRepository('SV\ReportImprovements:ReportQueue');
        return $entryRepo->getFauxReportQueueList();
    }

    public function getContentTitle(Entity $entity)
    {
        /** @var ReportQueueEntity $entity */
        return $entity->queue_name;
    }

    public function isValidPermission(\XF\Entity\Permission $permission)
    {
        return $permission->permission_group_id === 'report_queue' ||
               ($permission->permission_group_id === 'general' && $permission->permission_id === 'viewReports');
    }

    protected function getFinalPerms($contentId, array $calculated, array &$childPerms)
    {
        if (!isset($calculated['report_queue']))
        {
            $calculated['report_queue'] = [];
        }

        $final = $this->builder->finalizePermissionValues($calculated['report_queue']);

        if (empty($final['viewReports']))
        {
            $childPerms['general']['viewReports'] = 'deny';
        }
    }

    protected function getFinalAnalysisPerms($contentId, array $calculated, array &$childPerms)
    {
        $final = $this->builder->finalizePermissionValues($calculated);

        if (empty($final['general']['viewReports']))
        {
            $childPerms['general']['viewReports'] = 'deny';
        }

        return $final;
    }
}