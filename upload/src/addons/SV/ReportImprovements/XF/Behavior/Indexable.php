<?php

namespace SV\ReportImprovements\XF\Behavior;

use SV\ReportImprovements\XF\Entity\Report;
use SV\ReportImprovements\XF\Repository\Report as ReportRepo;
use XF\Entity\Report as ReportEntity;
use function array_key_exists;
use function assert;

/**
 * @extends \XF\Behavior\Indexable
 */
class Indexable extends XFCP_Indexable
{
    protected $svWasVisible = false;
    protected $svHasApprovalDeleteRelations = false;

    protected function svCheckVisibleRelations(): bool
    {
        $structure = $this->entity->structure();

        // by convention, ApprovalQueue/DeletionLog indicate if an entity is in the approval queue or deleted
        // but XF does not enforce this and the visible flag is entity dependant
        foreach (['ApprovalQueue', 'DeletionLog'] as $relation)
        {
            if (array_key_exists($relation, $structure->relations))
            {
                $this->svHasApprovalDeleteRelations = true;
                $relationObj = $this->entity->getRelation($relation);
                if ($relationObj !== null && $relationObj->exists())
                {
                    return false;
                }
            }
        }

        return true;
    }

    public function preSave()
    {
        $this->svWasVisible = $this->svCheckVisibleRelations();

        parent::preSave();
    }

    public function postSave()
    {
        parent::postSave();

        if ($this->svHasApprovalDeleteRelations)
        {
            $isVisible = $this->svCheckVisibleRelations();
            if ($isVisible !== $this->svWasVisible)
            {
                $this->triggerReportReIndex();
            }
            return;
        }

        $reportRepo = \SV\StandardLib\Helper::repository(\XF\Repository\Report::class);
        assert($reportRepo instanceof ReportRepo);
        if ($reportRepo->hasContentVisibilityChanged($this->entity))
        {
            $this->triggerReportReIndex();
        }
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
        $reportRepo = \SV\StandardLib\Helper::repository(\XF\Repository\Report::class);
        assert($reportRepo instanceof ReportRepo);
        $handler = $reportRepo->getReportHandler($contentType, false);
        if ($handler === null)
        {
            return;
        }

        $report = null;
        $structure = $this->entity->structure();
        if (array_key_exists('Report', $structure->relations))
        {
            $report = $this->entity->getRelation('Report');
        }
        if (!($report instanceof ReportEntity))
        {
            $report = \SV\StandardLib\Helper::finder(\XF\Finder\Report::class)
                           ->where('content_type', $contentType)
                           ->where('content_id', $this->entity->getEntityId())
                           ->fetchOne();
        }
        if ($report !== null)
        {
            assert($report instanceof Report);
            $report->triggerReindex(true);
        }
    }
}