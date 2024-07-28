<?php

namespace SV\ReportImprovements\XF\Finder;

use SV\ReportImprovements\ApprovalQueue\IContainerToContent;
use SV\StandardLib\Finder\SqlJoinTrait;
use XF\Repository\ApprovalQueue as ApprovalQueueRepo;
use function array_merge;

/**
 * @extends \XF\Finder\ApprovalQueue
 */
class ApprovalQueue extends XFCP_ApprovalQueue
{
    use SqlJoinTrait;

    public function getContainerToContentJoins(): array
    {
        $joins = [];
        /** @var ApprovalQueueRepo $approvalQueueRepo */
        $approvalQueueRepo = \SV\StandardLib\Helper::repository(\XF\Repository\ApprovalQueue::class);
        $handlers = $approvalQueueRepo->getApprovalQueueHandlers(false);
        foreach ($handlers as $handler)
        {
            if ($handler instanceof IContainerToContent)
            {
                $joins = array_merge($joins, $handler->getContainerToContentJoins());
            }
        }

        return $joins;
    }

    /**
     * @return $this
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function withoutReport()
    {
        $this->where('Report.report_id', null);

        $aliasCounter = 0;
        $joins = $this->getContainerToContentJoins();
        foreach ($joins as $containerEntityName => $details)
        {
            $entityName = $details['content'] ?? null;
            $contentKey = $details['contentKey'] ?? null;
            if ($containerEntityName === null || $contentKey === null)
            {
                continue;
            }
            try
            {
                $structure = \XF::app()->em()->getEntityStructure($entityName);
            }
            catch (\Throwable $e)
            {
                continue;
            }
            $contentType = $structure->contentType ?? '';
            if ($contentType === '')
            {
                continue;
            }

            try
            {
                $containerStructure = \XF::app()->em()->getEntityStructure($containerEntityName);
            }
            catch (\Throwable $e)
            {
                continue;
            }

            $containerContentType = $containerStructure->contentType ?? '';
            if ($containerContentType === '')
            {
                continue;
            }

            $containerAlias = 'svReportAlias_' . ($aliasCounter++);
            $this->sqlJoin($containerStructure->table, $containerAlias, [$contentKey], false);
            $this->sqlJoinConditions($containerAlias, [
                ['$content_type', '=', $containerContentType],
                [$containerStructure->primaryKey, '=', '$content_id'],
            ]);

            $contentReportAlias = 'svReportAlias_' . ($aliasCounter++);
            $this->sqlJoin('xf_report', $contentReportAlias, ['content_type', 'content_id'], false);
            $this->sqlJoinConditions($contentReportAlias, [
                ['content_type', '=', $contentType],
                ['content_id', '=', '$' . $containerAlias . '.' . $contentKey],
            ]);

            $this->where($contentReportAlias . '.content_id', null);
        }

        return $this;
    }

    /**
     * @return $this
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function withoutTicket()
    {
        $this->whereOr(
            ['content_type', '<>', 'user'],
            ['User.nf_tickets_count', '<>', 0]
        );

        return $this;
    }
}