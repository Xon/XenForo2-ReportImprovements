<?php

namespace SV\ReportImprovements\Search\Data;

use XF\Search\Data\AbstractData;
use XF\Mvc\Entity\Entity;
use XF\Search\MetadataStructure;
use XF\Search\IndexRecord;

/**
 * Class ReportComment
 *
 * @package SV\ReportImprovements\Search\Data
 */
class ReportComment extends AbstractData
{
    /**
     * @param bool $forView
     *
     * @return array
     */
    public function getEntityWith($forView = false)
    {
        return ['Report', 'User'];
    }

    /**
     * @param Entity|\SV\ReportImprovements\XF\Entity\ReportComment $entity
     *
     * @return mixed|null
     */
    public function getResultDate(Entity $entity)
    {
        return $entity->comment_date;
    }

    /**
     * @param Entity|\SV\ReportImprovements\XF\Entity\ReportComment $entity
     *
     * @return IndexRecord|null
     */
    public function getIndexData(Entity $entity)
    {
        if (!$entity->Report)
        {
            return null;
        }

        return IndexRecord::create('report', $entity->report_id, [
            'message' => '',
            'date' => $entity->comment_date,
            'user_id' => $entity->user_id,
            'discussion_id' => 0,
            'metadata' => $this->getMetaData($entity)
        ]);
    }

    /**
     * @param \XF\Entity\ReportComment|\SV\ReportImprovements\XF\Entity\ReportComment $entity
     *
     * @return array
     */
    protected function getMetaData(\XF\Entity\ReportComment $entity)
    {
        return [
            'report' => $entity->report_id,
            'report_state' => $entity->Report->report_state,
            'is_report' => $entity->is_report,
        ];
    }

    /**
     * @param Entity|\SV\ReportImprovements\XF\Entity\ReportComment $entity
     * @param array  $options
     *
     * @return array
     */
    public function getTemplateData(Entity $entity, array $options = [])
    {
        return [
            'report' => $entity->Report,
            'reportComment' => $entity,
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
        $structure->addField('is_report', MetadataStructure::INT);
    }
}