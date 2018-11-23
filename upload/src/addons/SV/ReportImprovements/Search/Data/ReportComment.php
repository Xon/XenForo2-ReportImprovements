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
     * @param Entity|\SV\ReportImprovements\XF\Entity\ReportComment $entity
     * @param null   $error
     *
     * @return bool
     */
    public function canViewContent(Entity $entity, &$error = null)
    {
        return $entity->Report->canView();
    }

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

        return IndexRecord::create('report_comment', $entity->report_comment_id, [
            'title' => $entity->Report->title,
            'message' => $entity->message,
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
        $metaData = [
            'report' => $entity->report_id,
            'report_state' => $entity->Report->report_state,
            'is_report' => $entity->is_report,
            'report_content_type' => $entity->Report->content_type
        ];

        if ($warningLog = $entity->WarningLog)
        {
            if ($warningLog->points)
            {
                $metaData['points'] = $warningLog->points;
            }

            if ($warningLog->expiry_date)
            {
                $metaData['expiry_date'] = $warningLog->expiry_date;
            }

            if ($warningLog->Warning && $warningLog->Warning->user_id)
            {
                $metaData['warned_user'] = $warningLog->Warning->user_id;
            }

            if ($warningLog->reply_ban_thread_id)
            {
                $metaData['thread_reply_ban'] = $warningLog->reply_ban_thread_id;
            }

            if ($warningLog->reply_ban_post_id)
            {
                $metaData['post_reply_ban'] = $warningLog->reply_ban_post_id;
            }
        }

        return $metaData;
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
     * @return array
     */
    public function getSearchableContentTypes()
    {
        return ['report_comment'];
    }

    /**
     * @param MetadataStructure $structure
     */
    public function setupMetadataStructure(MetadataStructure $structure)
    {
        $structure->addField('report', MetadataStructure::INT);
        $structure->addField('report_state', MetadataStructure::STR);
        $structure->addField('is_report', MetadataStructure::INT);
        $structure->addField('report_content_type', MetadataStructure::STR);
        $structure->addField('points', MetadataStructure::INT);
        $structure->addField('expiry_date', MetadataStructure::INT);
        $structure->addField('warned_user', MetadataStructure::INT);
        $structure->addField('thread_reply_ban', MetadataStructure::INT);
        $structure->addField('post_reply_ban', MetadataStructure::INT);
    }
}