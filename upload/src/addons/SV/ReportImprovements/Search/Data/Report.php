<?php

namespace SV\ReportImprovements\Search\Data;

use XF\Search\Data\AbstractData;
use XF\Mvc\Entity\Entity;
use XF\Search\MetadataStructure;
use XF\Search\IndexRecord;

/**
 * Class Report
 *
 * @package SV\ReportImprovements\Search\Data
 */
class Report extends AbstractData
{
    /**
     * @param Entity|\SV\ReportImprovements\XF\Entity\Report $entity
     * @param null   $error
     *
     * @return bool
     */
    public function canViewContent(Entity $entity, &$error = null)
    {
        return $entity->canView();
    }

    /**
     * @param Entity|\SV\ReportImprovements\XF\Entity\Report $entity
     *
     * @return int
     */
    public function getResultDate(Entity $entity)
    {
        return $entity->first_report_date;
    }

    /**
     * @param Entity|\SV\ReportImprovements\XF\Entity\Report $entity
     *
     * @return IndexRecord|null
     */
    public function getIndexData(Entity $entity)
    {
        if (!$entity->Content)
        {
            return null;
        }

        if (!$handler = $entity->getHandler())
        {
            return null;
        }

        return IndexRecord::create('report', $entity->report_id, [
            'title' => $handler->getContentTitle($entity),
            'message' => $handler->getContentMessage($entity),
            'date' => $entity->first_report_date,
            'user_id' => $entity->content_user_id,
            'discussion_id' => $entity->report_id,
            'metadata' => $this->getMetaData($entity)
        ]);
    }

    /**
     * @param \XF\Entity\Report|\SV\ReportImprovements\XF\Entity\Report $entity
     *
     * @return array
     */
    protected function getMetaData(\XF\Entity\Report $entity)
    {
        $metaData = [
            'report' => $entity->report_id,
            'report_state' => $entity->report_state,
            'assigned_user' => $entity->assigned_user_id,
            'is_report' => 2,
            'report_content_type' => $entity->content_type
        ];

        if (isset($entity->content_info['thread_id']))
        {
            $metaData['thread'] = $entity->content_info['thread_id'];
        }

        return $metaData;
    }

    /**
     * @param Entity $entity
     * @param array  $options
     *
     * @return array
     */
    public function getTemplateData(Entity $entity, array $options = [])
    {
        return [
            'report' => $entity,
            'options' => $options
        ];
    }

    /**
     * @param MetadataStructure $structure
     */
    public function setupMetadataStructure(MetadataStructure $structure)
    {
        $structure->addField('thread', MetadataStructure::INT);
        $structure->addField('report', MetadataStructure::INT);
        $structure->addField('report_state', MetadataStructure::KEYWORD);
        $structure->addField('report_content_type', MetadataStructure::KEYWORD);
        $structure->addField('assigned_user', MetadataStructure::INT);
        // must be an int, as ElasticSearch single index has this all mapped to the same type
        $structure->addField('is_report', MetadataStructure::INT);
    }
}