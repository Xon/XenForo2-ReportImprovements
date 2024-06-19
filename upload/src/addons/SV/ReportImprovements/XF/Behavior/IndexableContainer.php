<?php

namespace SV\ReportImprovements\XF\Behavior;

use SV\ReportImprovements\Job\ReindexReportsForContainer;
use SV\ReportImprovements\XF\Repository\Report as ReportRepo;
use function array_key_exists;
use function assert;

/**
 * @extends \XF\Behavior\IndexableContainer
 */
class IndexableContainer extends XFCP_IndexableContainer
{
    protected $svWasVisible = false;
    protected $svHasApprovalDeleteRelations = false;

    protected function svCheckVisibleRelations(bool $withExists): bool
    {
        $structure = $this->entity->structure();
        foreach (['ApprovalQueue', 'DeletionLog'] as $relation)
        {
            if (array_key_exists($relation, $structure->relations))
            {
                $this->svHasApprovalDeleteRelations = true;
                $relationObj = $this->entity->getRelation($relation);
                if ($relationObj !== null && (!$withExists || $relationObj->exists()))
                {
                    return false;
                }
            }
        }

        return true;
    }

    public function preSave()
    {
        $this->svWasVisible = $this->svCheckVisibleRelations(false);

        parent::preSave();
    }

    public function postSave()
    {
        parent::postSave();

        if ($this->svHasApprovalDeleteRelations)
        {
            $isVisible = $this->svCheckVisibleRelations(true);
            if ($isVisible !== $this->svWasVisible)
            {
                $this->triggerReportReIndex($this->getChildIds());
            }
            return;
        }

        $reportRepo = $this->repository('XF:Report');
        assert($reportRepo instanceof ReportRepo);
        if ($reportRepo->hasContentVisibilityChanged($this->entity))
        {
            $this->triggerReportReIndex($this->getChildIds());
        }
    }

    public function postDelete()
    {
        parent::postDelete();
        if ($this->onDeleteChildIds)
        {
            // content is hard deleted
            $this->triggerReportReIndex($this->onDeleteChildIds);
        }
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