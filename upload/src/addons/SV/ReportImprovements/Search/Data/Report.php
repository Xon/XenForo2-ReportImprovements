<?php

namespace SV\ReportImprovements\Search\Data;

use SV\ReportImprovements\Report\ReportSearchFormInterface;
use SV\SearchImprovements\Search\DiscussionTrait;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Search\Data\AbstractData;
use XF\Search\IndexRecord;
use XF\Search\MetadataStructure;

/**
 * Class Report
 *
 * @package SV\ReportImprovements\Search\Data
 */
class Report extends AbstractData
{
    protected static $svDiscussionEntity = \XF\Entity\Report::class;

    use DiscussionTrait;

    /** @var \SV\ReportImprovements\XF\Repository\Report */
    protected $reportRepo;

    public function __construct($contentType, \XF\Search\Search $searcher)
    {
        parent::__construct($contentType, $searcher);

        $this->reportRepo = \XF::repository('XF:Report');
    }

    /**
     * @param Entity|\SV\ReportImprovements\XF\Entity\Report $entity
     * @param null                                           $error
     * @return bool
     */
    public function canViewContent(Entity $entity, &$error = null)
    {
        return $entity->canView();
    }

    public function getContent($id, $forView = false)
    {
        $entities = parent::getContent($id, $forView);

        if ($entities instanceof AbstractCollection)
        {
            /** @var \SV\ReportImprovements\XF\Repository\Report $reportRepo */
            $reportRepo = \XF::repository('XF:Report');
            $reportRepo->svPreloadReports($entities);
        }

        return $entities;
    }

    public function getContentInRange($lastId, $amount, $forView = false)
    {
        $contents = parent::getContentInRange($lastId, $amount, $forView);
        /** @var \SV\ReportImprovements\XF\Repository\Report $reportRepo */
        $reportRepo = \XF::repository('XF:Report');
        $reportRepo->svPreloadReports($contents);

        return $contents;
    }

    /**
     * @param bool $forView
     * @return array
     */
    public function getEntityWith($forView = false)
    {
        $get = [];

        if ($forView)
        {
            $visitor = \XF::visitor();
            $get[] = 'Permissions|' . $visitor->permission_combination_id;
        }

        return $get;
    }

    /**
     * @param Entity|\SV\ReportImprovements\XF\Entity\Report $entity
     * @return int
     */
    public function getResultDate(Entity $entity)
    {
        return $entity->first_report_date;
    }

    /**
     * @param Entity $entity
     * @return IndexRecord|null
     */
    public function getIndexData(Entity $entity)
    {
        /** @var \SV\ReportImprovements\XF\Entity\Report $entity */
        if (!$entity->Content)
        {
            return null;
        }

        $handler = $entity->getHandler();
        if (!$handler)
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

    /**
     * @param \XF\Entity\Report|\SV\ReportImprovements\XF\Entity\Report $entity
     * @return array
     */
    protected function getMetaData(\XF\Entity\Report $entity)
    {
        $metaData = [
            'report'              => $entity->report_id,
            'report_state'        => $entity->report_state,
            'report_content_type' => $entity->content_type,
            'is_report'           => ReportComment::REPORT_TYPE_IS_REPORT,
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

    /**
     * @param Entity $entity
     * @param array  $options
     * @return array
     */
    public function getTemplateData(Entity $entity, array $options = [])
    {
        return [
            'report'  => $entity,
            'options' => $options,
        ];
    }

    /**
     * @param MetadataStructure $structure
     */
    public function setupMetadataStructure(MetadataStructure $structure)
    {
        foreach ($this->reportRepo->getReportHandlers() as $handler)
        {
            if ($handler instanceof ReportSearchFormInterface)
            {
                $handler->setupMetadataStructure($structure);
            }
        }
        $structure->addField('report', MetadataStructure::INT);
        $structure->addField('report_state', MetadataStructure::KEYWORD);
        $structure->addField('report_content_type', MetadataStructure::KEYWORD);
        $structure->addField('assigned_user', MetadataStructure::INT);
        $structure->addField('assigner_user', MetadataStructure::INT);
        // must be an int, as ElasticSearch single index has this all mapped to the same type
        $structure->addField('is_report', MetadataStructure::INT);

        $this->setupDiscussionMetadataStructure($structure);
    }
}