<?php

namespace SV\ReportImprovements\Search\Data;

use SV\ReportImprovements\XF\Entity\Report as ReportEntity;
use SV\SearchImprovements\Search\DiscussionTrait;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Entity;
use XF\Search\Data\AbstractData;
use XF\Search\IndexRecord;
use XF\Search\MetadataStructure;
use function assert;
use function is_array;

/**
 * Class Report
 *
 * @package SV\ReportImprovements\Search\Data
 */
class Report extends AbstractData
{
    protected static $svDiscussionEntity = \XF\Entity\Report::class;
    use DiscussionTrait;
    use SearchDataSetupTrait;

    public function canViewContent(Entity $entity, &$error = null): bool
    {
        assert($entity instanceof ReportEntity);
        return $entity->canView();
    }

    /**
     * @param int|int[] $id
     * @param bool $forView
     * @return AbstractCollection|array|Entity|null
     */
    public function getContent($id, $forView = false)
    {
        if (!$this->isAddonFullyActive)
        {
            // This function may be invoked when the add-on is disabled, just return nothing
            return is_array($id) ? [] : null;
        }

        $entities = parent::getContent($id, $forView);

        if ($entities instanceof AbstractCollection)
        {
            $this->reportRepo->svPreloadReports($entities);
        }

        return $entities;
    }

    /**
     * @param int $lastId
     * @param int $amount
     * @param bool $forView
     * @return AbstractCollection
     */
    public function getContentInRange($lastId, $amount, $forView = false)
    {
        if (!$this->isAddonFullyActive)
        {
            // This function may be invoked when the add-on is disabled, just return nothing
            return new ArrayCollection([]);
        }

        $contents = parent::getContentInRange($lastId, $amount, $forView);

        $this->reportRepo->svPreloadReports($contents);

        return $contents;
    }

    /**
     * @param bool $forView
     * @return array
     */
    public function getEntityWith($forView = false): array
    {
        $get = [];

        if ($forView)
        {
            $visitor = \XF::visitor();
            $get[] = 'Permissions|' . $visitor->permission_combination_id;
        }

        return $get;
    }

    public function getResultDate(Entity $entity): int
    {
        assert($entity instanceof ReportEntity);
        return $entity->first_report_date;
    }

    /**
     * @param Entity $entity
     * @return IndexRecord|null
     */
    public function getIndexData(Entity $entity): ?IndexRecord
    {
        if (!($entity instanceof ReportEntity))
        {
            // This function may be invoked when the add-on is disabled, just return nothing to index
            return null;
        }

        $handler = $entity->getHandler();
        if ($handler === null)
        {
            return null;
        }

        return IndexRecord::create('report', $entity->report_id, [
            'title'         => $entity->title_string,
            'message'       => $this->getMessage($entity),
            'date'          => $entity->first_report_date,
            'user_id'       => $entity->content_user_id,
            'discussion_id' => $entity->report_id,
            'metadata'      => $this->getMetaData($entity),
        ]);
    }

    protected function getMessage(ReportEntity $entity): string
    {
        try
        {
            $message = $entity->getHandler()->getContentMessage($entity);
        }
        catch (\Exception $e)
        {
            \XF::logException($e, false, 'Error accessing reported content for report ('.$entity->report_id.')');
            $message = '';
        }

        return $message;
    }

    protected function getMetaData(ReportEntity $report): array
    {
        $metaData = $this->reportRepo->getReportSearchMetaData($report);
        $this->populateDiscussionMetaData($report, $metaData);

        return $metaData;
    }

    public function getTemplateData(Entity $entity, array $options = []): array
    {
        assert($entity instanceof ReportEntity);
        return [
            'report'  => $entity,
            'options' => $options,
        ];
    }

    public function setupMetadataStructure(MetadataStructure $structure): void
    {
        $this->reportRepo->setupMetadataStructureForReport($structure);
        $this->setupDiscussionMetadataStructure($structure);
    }
}