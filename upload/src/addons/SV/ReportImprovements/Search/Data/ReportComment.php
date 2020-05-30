<?php

namespace SV\ReportImprovements\Search\Data;

use SV\ReportImprovements\Globals;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Search\Data\AbstractData;
use XF\Search\IndexRecord;
use XF\Search\MetadataStructure;
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
     * @param null                                                  $error
     * @return bool
     */
    public function canViewContent(Entity $entity, &$error = null)
    {
        return $entity->Report && $entity->Report->canView();
    }

    public function getContent($id, $forView = false)
    {
        $entities = parent::getContent($id, $forView);

        if ($entities instanceof AbstractCollection)
        {
            $this->svPreloadEntityData($entities);
        }


        return $entities;
    }

    public function getContentInRange($lastId, $amount, $forView = false)
    {
        $contents = parent::getContentInRange($lastId, $amount, $forView);
        $this->svPreloadEntityData($contents);

        return $contents;
    }

    public function svPreloadEntityData(AbstractCollection $contents)
    {
        /** @var \XF\Repository\Report $reportReport */
        $reportReport = \XF::repository('XF:Report');

        $reportsByContentType = [];
        $reports = [];
        /** @var \SV\ReportImprovements\XF\Entity\ReportComment $reportComment */
        foreach ($contents as $reportComment)
        {
            $reports[$reportComment->report_id] = $reportComment->Report;
        }

        /** @var \SV\ReportImprovements\XF\Entity\Report $report */
        foreach ($reports as $report)
        {
            if (!$report)
            {
                continue;
            }
            $contentType = $report->content_type;
            $handler = $reportReport->getReportHandler($contentType, false);
            if (!$handler)
            {
                continue;
            }

            $reportsByContentType[$contentType][$report->content_id] = $report;
        }

        foreach ($reportsByContentType as $contentType => $reports)
        {
            $handler = $reportReport->getReportHandler($contentType, false);
            if (!$handler)
            {
                continue;
            }
            $contentIds = array_keys($reports);
            if (!$contentIds)
            {
                continue;
            }
            $reportContents = $handler->getContent($contentIds);
            foreach ($reportContents as $contentId => $reportContent)
            {
                if (empty($reportsByContentType[$contentType][$contentId]))
                {
                    continue;
                }

                /** @var \SV\ReportImprovements\XF\Entity\Report $report */
                $report = $reportsByContentType[$contentType][$contentId];

                if ($reportContent)
                {
                    $report->setContent($reportContent);
                }
            }
        }

        return $contents;
    }

    /**
     * @param bool $forView
     * @return array
     */
    public function getEntityWith($forView = false)
    {
        return ['Report', 'User', 'WarningLog'];
    }

    /**
     * @param Entity|\SV\ReportImprovements\XF\Entity\ReportComment $entity
     * @return mixed|null
     */
    public function getResultDate(Entity $entity)
    {
        return $entity->comment_date;
    }

    /**
     * @param Entity|\SV\ReportImprovements\XF\Entity\ReportComment $entity
     * @return IndexRecord|null
     */
    public function getIndexData(Entity $entity)
    {
        if (!$entity->Report)
        {
            return null;
        }

        return IndexRecord::create('report_comment', $entity->report_comment_id, [
            'title'         => $entity->Report->title,
            'message'       => $entity->message,
            'date'          => $entity->comment_date,
            'user_id'       => $entity->user_id,
            'discussion_id' => $entity->report_id,
            'metadata'      => $this->getMetaData($entity),
        ]);
    }

    /**
     * @param \XF\Entity\ReportComment|\SV\ReportImprovements\XF\Entity\ReportComment $entity
     * @return array
     */
    protected function getMetaData(\XF\Entity\ReportComment $entity)
    {
        $metaData = [
            'report'       => $entity->report_id,
            'state_change' => $entity->state_change ?: '',
            'is_report'    => $entity->is_report ? 1 : 0, // must be an int
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

        if ($report = $entity->Report)
        {
            if (isset($report->content_info['thread_id']))
            {
                $metaData['thread'] = $report->content_info['thread_id'];
            }
        }

        return $metaData;
    }

    /**
     * @param Entity|\SV\ReportImprovements\XF\Entity\ReportComment $entity
     * @param array                                                 $options
     * @return array
     */
    public function getTemplateData(Entity $entity, array $options = [])
    {
        return [
            'report'        => $entity->Report,
            'reportComment' => $entity,
            'options'       => $options,
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
        $structure->addField('thread', MetadataStructure::INT);
        $structure->addField('report', MetadataStructure::INT);
        $structure->addField('state_change', MetadataStructure::KEYWORD);
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
     * @param Query            $query
     * @param \XF\Http\Request $request
     * @param array            $urlConstraints
     */
    public function applyTypeConstraintsFromInput(Query $query, \XF\Http\Request $request, array &$urlConstraints)
    {
        $constraints = $request->filter([
            'c.report.contents'     => 'bool',
            'c.report.comments'     => 'bool',
            'c.report.user_reports' => 'bool',

            'c.warning.user'         => 'str',
            'c.warning.points.lower' => 'uint',
            'c.warning.points.upper' => 'uint',
        ]);

        $isReport = [];
        if ($constraints['c.report.comments'])
        {
            $isReport[] = 0;
        }

        if ($constraints['c.report.user_reports'])
        {
            $isReport[] = 1;
        }

        if ($constraints['c.report.contents'])
        {
            $isReport[] = 2;
        }

        if (\count($isReport))
        {
            $query->withMetadata('is_report', $isReport);
        }

        $threadId = $request->filter('c.thread', 'uint');
        if ($threadId)
        {
            $query->withMetadata('thread', $threadId)
                  ->inTitleOnly(false);
        }

        if ($constraints['c.warning.user'])
        {
            $users = preg_split('/,\s*/', $constraints['c.warning.user'], -1, PREG_SPLIT_NO_EMPTY);
            if ($users)
            {
                /** @var \XF\Repository\User $userRepo */
                $userRepo = \XF::repository('XF:User');
                $matchedUsers = $userRepo->getUsersByNames($users, $notFound);
                if ($notFound)
                {
                    $query->error('users',
                        \XF::phrase('following_members_not_found_x', ['members' => implode(', ', $notFound)])
                    );
                }
                else
                {
                    $userIds = $matchedUsers->keys();
                    if ($userIds)
                    {
                        $query->withMetadata('warned_user', $userIds);
                    }
                    $urlConstraints['warning.user'] = implode(', ', $users);
                }
            }
        }

        $addOns = \XF::app()->container('addon.cache');
        if (isset($addOns['SV/SearchImprovements']))
        {
            // do not simplify these imports! Otherwise it will convert the soft-dependency into a hard dependency
            if ($constraints['c.warning.points.lower'] && $constraints['c.warning.points.upper'])
            {
                $query->withMetadata(new \SV\SearchImprovements\XF\Search\Query\RangeMetadataConstraint('points', [
                    $constraints['c.warning.points.upper'],
                    $constraints['c.warning.points.lower'],
                ],\SV\SearchImprovements\XF\Search\Query\RangeMetadataConstraint::MATCH_BETWEEN, $this->getWarningLogQueryTableReference()));
            }
            else if ($constraints['c.warning.points.lower'])
            {
                $query->withMetadata(new \SV\SearchImprovements\XF\Search\Query\RangeMetadataConstraint('points', $constraints['c.warning.points.lower'],
                    \SV\SearchImprovements\XF\Search\Query\RangeMetadataConstraint::MATCH_GREATER, $this->getWarningLogQueryTableReference()));
            }
            else if ($constraints['c.warning.points.upper'])
            {
                $query->withMetadata(new \SV\SearchImprovements\XF\Search\Query\RangeMetadataConstraint('points', $constraints['c.warning.points.upper'],
                    \SV\SearchImprovements\XF\Search\Query\RangeMetadataConstraint::MATCH_LESSER, $this->getWarningLogQueryTableReference()));
            }
        }
    }

    /**
     * This allows you to specify constraints to avoid including search results that will ultimately be filtered
     * out due to permissions.In most cases, the query should not generally be modified. It is passed in to allow inspection.
     * Note that your returned constraints may not be applied only to results of the relevant types. If possible, you
     * should only return "none" constraints using metadata keys that are unique to the involved content types.
     * $isOnlyType will be true when the search is specific to this type. This allows different constraints to be applied
     * when searching within the type. For example, this could implicitly disable searching of a content type unless targeted.
     *
     * @param Query $query      $query
     * @param bool  $isOnlyType Will be true if the search is specifically limited to this type.
     * @return MetadataConstraint[] Only an array of metadata constraints may be returned.
     */
    public function getTypePermissionConstraints(Query $query, $isOnlyType)
    {
        if (!Globals::$reportInAccountPostings)
        {
//            $bypass = new BypassAccessStatus();
//            $getter = $bypass->getPrivate($query, 'search');
//            /** @var \XF\Search\Search $search */
//            $search = $getter();
//            $getter = $bypass->getPrivate($search, 'source');
//            /** @var \XF\Search\Source\AbstractSource $source */
//            $source = $getter();
//            if ($source instanceof \XFES\Search\Source\Elasticsearch)
//            {
//                $getter = $bypass->getPrivate($source, 'es');
//                /** @var \XFES\Elasticsearch\Api $es */
//                $es = $getter();
//                if ($es->isSingleTypeIndex())
//                {
//
//                }
//            }
            // todo verify this works with ES5 or older
            return [
                new MetadataConstraint('type', 'report', 'none'),
            ];
        }

        // if a visitor can't view the username of a reporter, just prevent searching for reports by users
        /** @var \SV\ReportImprovements\XF\Entity\User $visitor */
        $visitor = \XF::visitor();
        if (!$visitor->canViewReporter())
        {
            return [
                new MetadataConstraint('is_report', [1], 'none'),
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

        if (!$visitor->canReportSearch())
        {
            return null;
        }

        return [
            'title' => \XF::phrase('svReportImprov_search_reports'),
            'order' => 250,
        ];
    }
}