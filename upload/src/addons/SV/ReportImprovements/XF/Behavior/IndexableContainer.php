<?php

namespace SV\ReportImprovements\XF\Behavior;

use SV\ReportImprovements\Job\ReindexReportsForContainer;
use XF\Repository\Report as ReportRepo;
use function assert;

/**
 * Extends \XF\Behavior\IndexableContainer
 */
class IndexableContainer extends XFCP_IndexableContainer
{
    public function postSave()
    {
        parent::postSave();
        // todo: try to detect visibility state changing
        //$this->triggerReportReIndex($this->getChildIds());
    }

    public function postDelete()
    {
        parent::postDelete();
        // content is hard deleted
        $this->triggerReportReIndex($this->onDeleteChildIds);
    }

    protected function triggerReportReIndex(array $childIds): void
    {
        if (count($childIds) === 0)
        {
            return;
        }

        $contentType = (string)($this->config['childContentType'] ?? '');
        $reportRepo = $this->repository('XF:Report');
        assert($reportRepo instanceof ReportRepo);
        $handler = $reportRepo->getReportHandler($contentType, false);
        if ($handler === null)
        {
            return;
        }

        // push to a job as this the number of reports is functionally unbound
        \XF::app()->jobManager()->enqueue(
            ReindexReportsForContainer::class,
            [
                'type' => $contentType,
                'ids'  => $childIds,
            ]);
    }
}