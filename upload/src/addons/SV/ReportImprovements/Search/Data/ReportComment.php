<?php

namespace SV\ReportImprovements\Search\Data;

use XF\Search\Data\AbstractData;
use XF\Mvc\Entity\Entity;
use XF\Search\MetadataStructure;
use XF\Search\IndexRecord;
use XF\Search\Query\MetadataConstraint;
use XF\Search\Query\Query;
use XF\Search\Query\TableReference;

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
        return ['Report', 'User', 'WarningLog'];
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
            'metadata' => $this->getMetaData($entity),
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
            'options' => $options,
        ];
    }

    /**
     * @return array
     */
    public function getSearchableContentTypes()
    {
        return ['report_comment', 'report'];
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
     * @param Query $query
     * @param \XF\Http\Request       $request
     * @param array                  $urlConstraints
     */
    public function applyTypeConstraintsFromInput(Query $query, \XF\Http\Request $request, array &$urlConstraints)
    {
        $constraints = $request->filter([
            'type'           => [
                'report_comment' => [
                    'include_report_contents' => 'bool',
                    'include_report_comments' => 'bool',
                    'include_user_reports'    => 'bool',
                ],
            ],
            'warning_points' => [
                'lower'  => 'uint',
                'higher' => 'uint',
            ],
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

        $addOns = \XF::app()->container('addon.cache');
        if (isset($addOns['SV/SearchImprovements']))
        {
            // do not simplify these imports! Otherwise it will convert the soft-dependency into a hard dependency
            if ($constraints['warning_points']['lower'])
            {
                $query->withMetadata(new \SV\SearchImprovements\XF\Search\Query\RangeMetadataConstraint('warning_log.points', $constraints['warning_points']['lower'],
                    \SV\SearchImprovements\XF\Search\Query\RangeMetadataConstraint::MATCH_GREATER, $this->getWarningLogQueryTableReference()));
            }

            if ($constraints['warning_points']['higher'])
            {
                $query->withMetadata(new \SV\SearchImprovements\XF\Search\Query\RangeMetadataConstraint('warning_log.points', $constraints['warning_points']['lower'],
                    \SV\SearchImprovements\XF\Search\Query\RangeMetadataConstraint::MATCH_LESSER, $this->getWarningLogQueryTableReference()));
            }
        }
    }

    /**
     * This allows you to specify constraints to avoid including search results that will ultimately be filtered
     * out due to permissions.In most cases, the query should not generally be modified. It is passed in to allow inspection.
     *
     * Note that your returned constraints may not be applied only to results of the relevant types. If possible, you
     * should only return "none" constraints using metadata keys that are unique to the involved content types.
     *
     * $isOnlyType will be true when the search is specific to this type. This allows different constraints to be applied
     * when searching within the type. For example, this could implicitly disable searching of a content type unless targeted.
     *
     * @param Query $query $query
     * @param bool $isOnlyType Will be true if the search is specifically limited to this type.
     *
     * @return MetadataConstraint[] Only an array of metadata constraints may be returned.
     */
    public function getTypePermissionConstraints(Query $query, $isOnlyType)
    {
        // if a visitor can't view the username of a reporter, just prevent searching for reports by users
        /** @var \SV\ReportImprovements\XF\Entity\User $visitor */
        $visitor = \XF::visitor();
        if (!$visitor->canViewReporter())
        {
            return [
                new MetadataConstraint('is_report', [1], 'none')
            ];
        }

        return [];
    }

    /**
     * @return TableReference[]
     */
    protected function getWarningLogQueryTableReference()
    {
        return [
            new TableReference(
                'report_comment',
                'xf_report_comment',
                'report_comment.report_comment_id = search_index.content_id'
            ),
            new TableReference(
                'warning_log',
                'xf_sv_warning_log',
                'warning_log.warning_log_id = report_comment.warning_log_id'
            ),
        ];
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