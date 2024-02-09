<?php

namespace SV\ReportImprovements\XF\Finder;

use SV\StandardLib\Finder\SqlJoinTrait;

/**
 * @extends \XF\Finder\ApprovalQueue
 */
class ApprovalQueue extends XFCP_ApprovalQueue
{
    use SqlJoinTrait;

    public function getContainerToContentJoins(): array
    {
        return [
            'XF:Post' => [
                'container' => 'XF:Thread',
                'contentKey' => 'first_post_id',
            ],
        ];
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
        foreach ($joins as $entityName => $details)
        {
            $containerEntityName = $details['container'] ?? null;
            $contentKey = $details['contentKey'] ?? null;
            if ($containerEntityName === null || $contentKey === null)
            {
                continue;
            }
            try
            {
                $structure = $this->em->getEntityStructure($entityName);
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
                $containerStructure = $this->em->getEntityStructure($containerEntityName);
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