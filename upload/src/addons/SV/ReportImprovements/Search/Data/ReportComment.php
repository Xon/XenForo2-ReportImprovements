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
            'state_change' => $entity->state_change ?: '',
            'is_report' => $entity->is_report ? 1 : 0, // must be an int
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
        $structure->addField('state_change', MetadataStructure::STR);
        // must be an int, as ElasticSearch single index has this all mapped to the same type
        $structure->addField('is_report', MetadataStructure::INT);
        // warning bits
        $structure->addField('points', MetadataStructure::INT);
        $structure->addField('expiry_date', MetadataStructure::INT);
        $structure->addField('warned_user', MetadataStructure::INT);
        $structure->addField('thread_reply_ban', MetadataStructure::INT);
        $structure->addField('post_reply_ban', MetadataStructure::INT);
    }

    /**
     * @param \XF\Search\Query\Query $query
     * @param \XF\Http\Request       $request
     * @param array                  $urlConstraints
     */
    public function applyTypeConstraintsFromInput(\XF\Search\Query\Query $query, \XF\Http\Request $request, array &$urlConstraints)
    {
        $constraints = $request->filter([
            'type' => [
                'report_comment' => [
                    'include_report_contents' => 'bool',
                    'include_report_comments' => 'bool',
                    'include_user_reports' => 'bool'
                ]
            ],
            'warning_points' => [
                'lower' => 'uint',
                'higher' => 'uint'
            ]
        ]);

        $isReport = [];
        if ($constraints['type']['report_comment']['include_report_comments'])
        {
            $isReport[] = 0;
        }

        if ($constraints['type']['report_comment']['include_user_reports'])
        {
            $isReport[] = 1;
        }

        if ($constraints['type']['report_comment']['include_report_contents'])
        {
            $isReport[] = 2;
        }

        if (\count($isReport))
        {
            $query->withMetadata('is_report', $isReport);
        }

        if ($constraints['warning_points']['lower'])
        {
            $query->withSql(new \XF\Search\Query\SqlConstraint(
                'warning_log.points >= %s',
                $constraints['warning_points']['lower'],
                $this->getWarningLogQueryTableReference()
            ));
        }

        if ($constraints['warning_points']['higher'])
        {
            $query->withSql(new \XF\Search\Query\SqlConstraint(
                'warning_log.points <= %s',
                $constraints['warning_points']['lower'],
                $this->getWarningLogQueryTableReference()
            ));
        }
    }

    /**
     * @return \XF\Search\Query\TableReference
     */
    protected function getWarningLogQueryTableReference()
    {
        return new \XF\Search\Query\TableReference(
            'warning_log',
            'xf_sv_warning_log',
            'warning_log.report_comment_id = search_index.content_id'
        );
    }
}