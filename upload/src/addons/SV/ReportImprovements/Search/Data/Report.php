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
            'discussion_id' => 0,
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
        return [
            'report' => $entity->report_id,
            'report_state' => $entity->report_state,
            'assigned_user' => $entity->assigned_user_id,
            'is_report' => 2,
            'content_type' => $entity->content_type
        ];
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
        $structure->addField('report', MetadataStructure::INT);
        $structure->addField('report_state', MetadataStructure::STR);
        $structure->addField('assigned_user_id', MetadataStructure::INT);
        $structure->addField('is_report', MetadataStructure::INT); // not bool?????
    }

    /**
     * @return array
     */
    public function getSearchableContentTypes()
    {
        return ['report', 'report_comment'];
    }

    /**
     * @return array|null
     */
    public function getSearchFormTab()
    {
        /** @var \SV\ReportImprovements\XF\Entity\User $visitor */
        $visitor = \XF::visitor();

        if (!method_exists($visitor, 'canViewReports') || !$visitor->canViewReports($error))
        {
            return null;
        }

        return [
            'title' => \XF::phrase('svReportImprov_search_reports'),
            'order' => 250
        ];
    }
}