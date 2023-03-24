<?php

namespace SV\ReportImprovements\Search\Data;

use SV\ReportImprovements\Enums\ReportType;
use SV\ReportImprovements\Report\ReportSearchFormInterface;
use SV\ReportImprovements\XF\Entity\Report as ReportEntity;
use SV\ReportImprovements\XF\Repository\Report as ReportRepo;
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

    /** @var ReportRepo */
    protected $reportRepo;

    /**
     * @param string            $contentType
     * @param \XF\Search\Search $searcher
     */
    public function __construct($contentType, \XF\Search\Search $searcher)
    {
        parent::__construct($contentType, $searcher);

        $this->reportRepo = \XF::repository('XF:Report');
    }

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
        $reportRepo = \XF::repository('XF:Report');
        if (!($reportRepo instanceof ReportRepo))
        {
            // This function may be invoked when the add-on is disabled, just return nothing
            return is_array($id) ? [] : null;
        }

        $entities = parent::getContent($id, $forView);

        if ($entities instanceof AbstractCollection)
        {
            $reportRepo->svPreloadReports($entities);
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
        $reportRepo = \XF::repository('XF:Report');
        if (!($reportRepo instanceof ReportRepo))
        {
            // This function may be invoked when the add-on is disabled, just return nothing
            return new ArrayCollection([]);
        }

        $contents = parent::getContentInRange($lastId, $amount, $forView);

        $reportRepo->svPreloadReports($contents);

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
        if ($handler == null)
        {
            return null;
        }

        try
        {
            $message = $handler->getContentMessage($entity);
        }
        catch (\Exception $e)
        {
            \XF::logException($e, false, 'Error accessing reported content for report ('.$entity->report_id.')');
            $message = '';
        }

        return IndexRecord::create('report', $entity->report_id, [
            'title'         => $entity->title_string,
            'message'       => $message,
            'date'          => $entity->first_report_date,
            'user_id'       => $entity->content_user_id,
            'discussion_id' => $entity->report_id,
            'metadata'      => $this->getMetaData($entity),
        ]);
    }

    protected function getMetaData(ReportEntity $entity): array
    {
        $metaData = [
            'report'              => $entity->report_id,
            'report_state'        => $entity->report_state,
            'report_content_type' => $entity->content_type,
            'report_type'         => ReportType::Reported_content,
            'content_user'        => $entity->content_user_id, // duplicate of report.user_id
        ];

        if ($entity->assigner_user_id)
        {
            $metaData['assigner_user'] = $entity->assigner_user_id;
        }

        if ($entity->assigned_user_id)
        {
            $metaData['assigned_user'] = $entity->assigned_user_id;
        }

        $reportHandler = $this->reportRepo->getReportHandler($entity->content_type, null);
        if ($reportHandler instanceof ReportSearchFormInterface)
        {
            $reportHandler->populateMetaData($entity, $metaData);
        }

        $this->populateDiscussionMetaData($entity, $metaData);

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
        foreach ($this->reportRepo->getReportHandlers() as $handler)
        {
            if ($handler instanceof ReportSearchFormInterface)
            {
                $handler->setupMetadataStructure($structure);
            }
        }
        $structure->addField('report_type', MetadataStructure::KEYWORD);
        $structure->addField('report', MetadataStructure::INT);
        $structure->addField('report_state', MetadataStructure::KEYWORD);
        $structure->addField('report_content_type', MetadataStructure::KEYWORD);
        $structure->addField('content_user', MetadataStructure::INT);
        $structure->addField('assigned_user', MetadataStructure::INT);
        $structure->addField('assigner_user', MetadataStructure::INT);

        $this->setupDiscussionMetadataStructure($structure);
    }
}