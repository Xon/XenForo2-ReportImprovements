<?php

namespace SV\ReportImprovements\XF\Behavior;

use SV\ReportImprovements\XF\Entity\Report;
use XF\Repository\Report as ReportRepo;
use function assert;

/**
 * Extends \XF\Behavior\Indexable
 */
class Indexable extends XFCP_Indexable
{
    public function postSave()
    {
        parent::postSave();
        // todo: try to detect visibility state changing
    }

    public function postDelete()
    {
        parent::postDelete();
        // content is hard deleted
        $this->triggerReportReIndex();
    }

    protected function triggerReportReIndex(): void
    {
        $contentType = (string)$this->contentType();
        $reportRepo = $this->repository('XF:Report');
        assert($reportRepo instanceof ReportRepo);
        $handler = $reportRepo->getReportHandler($contentType, false);
        if ($handler === null)
        {
            return;
        }

        $report = $this->app()
                       ->finder('XF:Report')
                       ->where('content_type', $contentType)
                       ->where('content_id', $this->entity->getEntityId())
                       ->fetchOne();
        if ($report !== null)
        {
            assert($report instanceof Report);
            $report->triggerReindex(true);
        }
    }
}