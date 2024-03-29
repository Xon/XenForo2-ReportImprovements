<?php

namespace SV\ReportImprovements\Job;

use SV\ReportImprovements\XF\Entity\Report;
use XF\Job\AbstractRebuildJob;
use function array_merge;
use function assert;

class ReindexReportsForContainer extends AbstractRebuildJob
{
    protected $optionData = [
        'type' => null,
        'ids' => null,
    ];

    protected function setupData(array $data)
    {
        $this->defaultData = array_merge($this->optionData, $this->defaultData);

        return parent::setupData($data);
    }

    protected function getNextIds($start, $batch): array
    {
        $contentType = $this->data['type'] ?? null;
        $contentIds = $this->data['ids'] ?? null;

        if (empty($contentType) || empty($contentIds))
        {
            return [];
        }

        $db = $this->app->db();

        return $db->fetchAllColumn($db->limit('
            SELECT report_id
            FROM xf_report
            WHERE content_type = ? 
              AND report_id > ? 
              AND content_id in (' . $db->quote($contentIds) . ')
        ', $batch), [$contentType, $start]);
    }

    protected function rebuildById($id): void
    {
        $report = $this->app->find('XF:Report', $id);
        if ($report === null)
        {
            return;
        }
        assert($report instanceof Report);
        $report->triggerReindex(true);
    }

    protected function getStatusType()
    {
        return null;
    }
}