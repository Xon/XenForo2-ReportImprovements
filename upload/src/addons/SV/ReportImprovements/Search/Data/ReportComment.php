<?php

namespace SV\ReportImprovements\Search\Data;

use SV\ReportImprovements\Globals;
use SV\SearchImprovements\XF\Search\Query\Constraints\RangeConstraint;
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
    const REPORT_TYPE_COMMENT = 0;
    const REPORT_TYPE_USER_REPORT = 1;
    const REPORT_TYPE_IS_REPORT = 2;

    /**
     * @param Entity|\SV\ReportImprovements\XF\Entity\ReportComment $entity
     * @param null                                                  $error
     * @return bool
     */
    public function canViewContent(Entity $entity, &$error = null)
    {
        $report = $entity->Report;
        return $report && $report->canView();
    }

    public function getContent($id, $forView = false)
    {
        $entities = parent::getContent($id, $forView);

        if ($entities instanceof AbstractCollection)
        {
            /** @var \SV\ReportImprovements\XF\Repository\Report $reportRepo */
            $reportRepo = \XF::repository('XF:Report');
            $reportRepo->svPreloadReportComments($entities);
        }

        return $entities;
    }

    public function getContentInRange($lastId, $amount, $forView = false)
    {
        $contents = parent::getContentInRange($lastId, $amount, $forView);
        /** @var \SV\ReportImprovements\XF\Repository\Report $reportRepo */
        $reportRepo = \XF::repository('XF:Report');
        $reportRepo->svPreloadReportComments($contents);

        return $contents;
    }

    /**
     * @param bool $forView
     * @return array
     */
    public function getEntityWith($forView = false)
    {
        $get = ['Report', 'User', 'WarningLog'];

        if ($forView)
        {
            $visitor = \XF::visitor();
            $get[] = 'Report.Permissions|' . $visitor->permission_combination_id;
        }

        return $get;
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
        /** @var \SV\ReportImprovements\XF\Entity\Report $report */
        $report = $entity->Report;
        if (!$report)
        {
            return null;
        }

        return IndexRecord::create('report_comment', $entity->report_comment_id, [
            'title'         => $report->title_string,
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
        $report = $entity->Report;
        $metaData = [
            'report'              => $entity->report_id,
            'report_state'        => $report->report_state,
            'assigned_user'       => $report->assigned_user_id,
            'assigner_user'       => $report->assigner_user_id,
            'report_content_type' => $report->content_type,
            'state_change'        => $entity->state_change ?: '',
            'is_report'           => $entity->is_report ? static::REPORT_TYPE_USER_REPORT : static::REPORT_TYPE_COMMENT, // must be an int
            'report_user'         => $report->content_user_id,
        ];

        $warningLog = $entity->WarningLog;
        if ($warningLog !== null)
        {
            if ($warningLog->points)
            {
                $metaData['points'] = $warningLog->points;
            }

            if ($warningLog->expiry_date)
            {
                $metaData['expiry_date'] = $warningLog->expiry_date;
            }

            $warning = $warningLog->Warning;
            if ($warning && $warning->user_id)
            {
                $metaData['warned_user'] = $warning->user_id;
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

        if ($report !== null)
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
     * @return string
     */
    public function getGroupByType()
    {
        return 'report';
    }

    /**
     * @param MetadataStructure $structure
     */
    public function setupMetadataStructure(MetadataStructure $structure)
    {
        $structure->addField('report_user', MetadataStructure::INT);
        // shared with Report
        $structure->addField('thread', MetadataStructure::INT);
        $structure->addField('report', MetadataStructure::INT);
        $structure->addField('state_change', MetadataStructure::KEYWORD);
        $structure->addField('report_state', MetadataStructure::KEYWORD);
        $structure->addField('report_content_type', MetadataStructure::KEYWORD);
        $structure->addField('assigned_user', MetadataStructure::INT);
        $structure->addField('assigner_user', MetadataStructure::INT);
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
            $isReport[] = static::REPORT_TYPE_COMMENT;
        }

        if ($constraints['c.report.user_reports'])
        {
            $isReport[] = static::REPORT_TYPE_USER_REPORT;
        }

        if ($constraints['c.report.contents'])
        {
            $isReport[] = static::REPORT_TYPE_IS_REPORT;
        }

        if (\count($isReport))
        {
            $query->withMetadata('is_report', $isReport);
        }

        $threadId = $request->filter('c.thread', 'uint');
        if ($threadId)
        {
            $query->withMetadata('thread', $threadId);

            if (\is_callable([$query, 'inTitleOnly']))
            {
                $query->inTitleOnly(false);
            }
        }

        if ($constraints['c.warning.user'])
        {
            $users = \preg_split('/,\s*/', $constraints['c.warning.user'], -1, PREG_SPLIT_NO_EMPTY);
            if ($users)
            {
                /** @var \XF\Repository\User $userRepo */
                $userRepo = \XF::repository('XF:User');
                $matchedUsers = $userRepo->getUsersByNames($users, $notFound);
                if ($notFound)
                {
                    $query->error('users',
                        \XF::phrase('following_members_not_found_x', ['members' => \implode(', ', $notFound)])
                    );
                }
                else
                {
                    $userIds = $matchedUsers->keys();
                    if ($userIds)
                    {
                        $query->withMetadata('warned_user', $userIds);
                    }
                    $urlConstraints['warning']['user'] = \implode(', ', $users);
                }
            }
        }

        $addOns = \XF::app()->container('addon.cache');
        if (isset($addOns['SV/SearchImprovements']))
        {
            $source = isset($addOns['SV/XFES']) ? 'search_index' : 'warning_log';
            if ($constraints['c.warning.points.lower'] && $constraints['c.warning.points.upper'])
            {
                $query->withMetadata(new RangeConstraint('points', [
                    $constraints['c.warning.points.upper'],
                    $constraints['c.warning.points.lower'],
                ], RangeConstraint::MATCH_BETWEEN, $this->getWarningLogQueryTableReference(), $source));
            }
            else if ($constraints['c.warning.points.lower'])
            {
                unset($urlConstraints['warning']['points']['upper']);
                $query->withMetadata(new RangeConstraint('points', $constraints['c.warning.points.lower'],
                    RangeConstraint::MATCH_GREATER, $this->getWarningLogQueryTableReference(), $source));
            }
            else if ($constraints['c.warning.points.upper'])
            {
                unset($urlConstraints['warning']['points']['lower']);
                $query->withMetadata(new RangeConstraint('points', $constraints['c.warning.points.upper'],
                    RangeConstraint::MATCH_LESSER, $this->getWarningLogQueryTableReference(), $source));
            }
            else
            {
                unset($urlConstraints['warning']['points']['upper']);
                unset($urlConstraints['warning']['points']['lower']);
            }
        }
        else
        {
            unset($urlConstraints['warning']['points']['upper']);
            unset($urlConstraints['warning']['points']['lower']);
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
                new MetadataConstraint('is_report', [static::REPORT_TYPE_USER_REPORT], 'none'),
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

        if (!\is_callable([$visitor, 'canReportSearch']) || !$visitor->canReportSearch())
        {
            return null;
        }

        return [
            'title' => \XF::phrase('svReportImprov_search_reports'),
            'order' => 250,
        ];
    }
}