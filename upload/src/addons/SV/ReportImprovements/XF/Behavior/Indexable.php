<?php

namespace SV\ReportImprovements\XF\Behavior;

use SV\ReportImprovements\XF\Entity\Report as ExtendedReportEntity;
use SV\ReportImprovements\XF\Repository\Report as ExtendedReportRepo;
use SV\StandardLib\Helper;
use XF\Entity\Report as ReportEntity;
use XF\Finder\Report as ReportFinder;
use XF\Repository\Report as ReportRepo;
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

        /** @var ExtendedReportRepo $reportRepo */
        $reportRepo = Helper::repository(ReportRepo::class);
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
        /** @var ExtendedReportRepo $reportRepo */
        $reportRepo = Helper::repository(ReportRepo::class);
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
            $report = Helper::finder(ReportFinder::class)
                            ->where('content_type', $contentType)
                            ->where('content_id', $this->entity->getEntityId())
                            ->fetchOne();
        }
        if ($report !== null)
        {
            /** @var ExtendedReportEntity $report */
            $report->triggerReindex(true);
        }
    }
}