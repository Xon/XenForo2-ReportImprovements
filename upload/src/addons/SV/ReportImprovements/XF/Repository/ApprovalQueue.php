<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace SV\ReportImprovements\XF\Repository;

use SV\ReportImprovements\ApprovalQueue\IContainerToContent;
use SV\ReportImprovements\XF\Entity\ApprovalQueue as ExtendedApprovalQueueEntity;
use SV\ReportImprovements\XF\Repository\ApprovalQueue as ExtendedApprovalQueueRepo;
use SV\StandardLib\Helper;
use XF\Entity\User as UserEntity;
use XF\Finder\Report as ReportFinder;
use XF\Mvc\Entity\AbstractCollection;
use XF\Repository\ApprovalQueue as ApprovalQueueRepository;
use XF\Repository\Report as ReportRepository;
use function array_merge;
use function count;
use function key;

/**
 * @extends ApprovalQueueRepository
 */
class ApprovalQueue extends XFCP_ApprovalQueue
{
    public function getUserDefaultFilters(UserEntity $user)
    {
        return $user->Option->sv_reportimprov_approval_filters ?? [];
    }

    public function saveUserDefaultFilters(UserEntity $user, array $filters)
    {
        $user->Option->fastUpdate('sv_reportimprov_approval_filters', $filters ?: null);
    }

    public function addReportsToUnapprovedItems(AbstractCollection $unapprovedItems): void
    {
        /** @var array<string,array<int,ExtendedApprovalQueueEntity>> $itemsByContentTypes */
        $itemsByContentTypes = [];
        /** @var ExtendedApprovalQueueEntity $unapprovedItem */
        foreach ($unapprovedItems as $unapprovedItem)
        {
            $unapprovedItem->hydrateRelation('Report', null);
            $itemsByContentTypes[$unapprovedItem->content_type][$unapprovedItem->content_id] = $unapprovedItem;
        }

        /** @var ReportRepository $reportRepo */
        $reportRepo = Helper::repository(ReportRepository::class);
        /** @var ExtendedApprovalQueueRepo $approvalQueueRepo */
        $approvalQueueRepo = Helper::repository(ApprovalQueueRepository::class);

        /** @var array<string,array<int,ExtendedApprovalQueueEntity>> $reportsToFetch */
        $reportsToFetch = [];
        foreach ($itemsByContentTypes as $contentType => $items)
        {
            // check the simple case
            if ($reportRepo->getReportHandler($contentType, false) !== null)
            {
                $reportsToFetch[$contentType] = array_merge($reportsToFetch[$contentType] ?? [], $items);
                continue;
            }

            $handler = $approvalQueueRepo->getApprovalQueueHandler($contentType);
            if ($handler instanceof IContainerToContent)
            {
                $joins = $handler->getContainerToContentJoins();
                // only support a single layer of redirection
                if (count($joins) === 1)
                {
                    $details = reset($joins);
                    $containerEntity = key($joins);
                    $entityName = $details['content'] ?? null;
                    $contentKey = $details['contentKey'] ?? null;
                    try
                    {
                        $containerStructure = \XF::em()->getEntityStructure($containerEntity);
                    }
                    catch (\Throwable $e)
                    {
                        $containerStructure = null;
                    }
                    try
                    {
                        $entityStructure = \XF::em()->getEntityStructure($entityName);
                    }
                    catch (\Throwable $e)
                    {
                        $entityStructure = null;
                    }
                    $contentType = $entityStructure->contentType ?? '';
                    if ($contentType !== '' && $containerStructure->columns[$contentKey] ?? false)
                    {
                        foreach ($items as $entity)
                        {
                            $content = $entity->Content;
                            if ($content !== null)
                            {
                                $id = $content->get($contentKey);
                                $reportsToFetch[$contentType][$id] = $entity;
                            }
                        }
                    }
                }
            }
        }

        $conditions = [];
        foreach ($reportsToFetch as $contentType => $ids)
        {
            $conditions[] = [
                'content_type' => $contentType,
                'content_id'   => array_keys($ids),
            ];
        }
        if (count($conditions) === 0)
        {
            return;
        }

        $reports = Helper::finder(ReportFinder::class)
                         ->with('LastModified')
                         ->whereOr($conditions)
                         ->fetch();
        foreach ($reports as $report)
        {
            $contentType = $report->content_type;
            $contentId = $report->content_id;
            $item = $reportsToFetch[$contentType][$contentId] ?? null;
            if ($item === null)
            {
                continue;
            }

            $item->hydrateRelation('Report', $report);
            $report->setContent($item->Content);
        }
    }
}