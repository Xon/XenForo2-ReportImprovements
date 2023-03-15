<?php

namespace SV\ReportImprovements\Search\Data;

use SV\ReportImprovements\Entity\WarningLog;
use SV\ReportImprovements\Globals;
use SV\ReportImprovements\Report\ReportSearchFormInterface;
use SV\ReportImprovements\XF\Repository\Report as ReportRepo;
use SV\SearchImprovements\Search\DiscussionTrait;
use SV\SearchImprovements\Util\Arr;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Search\Data\AbstractData;
use XF\Search\IndexRecord;
use XF\Search\MetadataStructure;
use XF\Search\Query\MetadataConstraint;
use XF\Search\Query\Query;
use XF\Search\Query\TableReference;
use function array_filter;
use function array_key_exists;
use function array_merge;
use function array_unique;
use function assert;
use function count;
use function in_array;
use function is_array;

/**
 * Class ReportComment
 *
 * @package SV\ReportImprovements\Search\Data
 */
class ReportComment extends AbstractData
{
    protected static $svDiscussionEntity = \XF\Entity\Report::class;
    use DiscussionTrait;

    const REPORT_TYPE_COMMENT = 0;
    const REPORT_TYPE_USER_REPORT = 1;
    const REPORT_TYPE_IS_REPORT = 2;

    /** @var \SV\ReportImprovements\XF\Repository\Report */
    protected $reportRepo;

    public function __construct($contentType, \XF\Search\Search $searcher)
    {
        parent::__construct($contentType, $searcher);

        $this->reportRepo = \XF::repository('XF:Report');
    }

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
            /** @var ReportRepo $reportRepo */
            $reportRepo = \XF::repository('XF:Report');
            $reportRepo->svPreloadReportComments($entities);
        }

        return $entities;
    }

    public function getContentInRange($lastId, $amount, $forView = false)
    {
        $contents = parent::getContentInRange($lastId, $amount, $forView);
        /** @var ReportRepo $reportRepo */
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

        $message = $entity->message;

        $warningLog = $entity->WarningLog;
        if ($warningLog !== null)
        {
            $message = $this->addWarningLogToMessage($warningLog, $message);
        }

        return IndexRecord::create('report_comment', $entity->report_comment_id, [
            'title'         => $report->title_string,
            'message'       => $message,
            'date'          => $entity->comment_date,
            'user_id'       => $entity->user_id,
            'discussion_id' => $entity->report_id,
            'metadata'      => $this->getMetaData($entity),
        ]);
    }

    protected function addWarningLogToMessage(WarningLog $warningLog, string $message): string
    {
        foreach ($warningLog->structure()->columns as $column => $schema)
        {
            if (
                ($schema['type'] ?? '') !== Entity::STR ||
                empty($schema['allowedValues']) || // aka enums
                ($schema['noIndex'] ?? false)
            )
            {
                continue;
            }

            $value = $warningLog->get($column);
            if ($value === null || $value === '')
            {
                continue;
            }

            $message .= "\n" . $value;
        }

        return $message;
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
            'report_content_type' => $report->content_type,
            'state_change'        => $entity->state_change ?: '',
            'is_report'           => $entity->is_report ? static::REPORT_TYPE_USER_REPORT : static::REPORT_TYPE_COMMENT, // must be an int
            'report_user'         => $report->content_user_id,
        ];

        if ($report->assigner_user_id)
        {
            $metaData['assigner_user'] = $report->assigner_user_id;
        }

        if ($report->assigned_user_id)
        {
            $metaData['assigned_user'] = $report->assigned_user_id;
        }

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

        $reportHandler = $this->reportRepo->getReportHandler($report->content_type, null);
        if ($reportHandler instanceof ReportSearchFormInterface)
        {
            $reportHandler->populateMetaData($report, $metaData);
        }

        $this->populateDiscussionMetaData($entity, $metaData);

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
        $this->reportRepo->getReportHandlers();

        $structure->addField('report_user', MetadataStructure::INT);
        // shared with Report
        foreach ($this->reportRepo->getReportHandlers() as $handler)
        {
            if ($handler instanceof ReportSearchFormInterface)
            {
                $handler->setupMetadataStructure($structure);
            }
        }
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

        $this->setupDiscussionMetadataStructure($structure);
    }

    /**
     * @param Query            $query
     * @param \XF\Http\Request $request
     * @param array            $urlConstraints
     */
    public function applyTypeConstraintsFromInput(Query $query, \XF\Http\Request $request, array &$urlConstraints)
    {
        $constraints = $request->filter([
            'c.assigned'         => 'str',
            'c.assigner'         => 'str',

            'c.replies.lower'       => 'uint',
            'c.replies.upper'       => 'uint',

            'c.report.type'         => 'array-str',
            'c.report.state'        => 'array-str',
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

        if (count($isReport) !== 0)
        {
            $query->withMetadata('is_report', $isReport);
        }

        $reportStates = $constraints['c.report.state'];
        assert(is_array($reportStates));
        if (count($reportStates) !== 0 && !in_array('0', $reportStates, true))
        {
            $reportStates = array_unique($reportStates);

            $states = $this->reportRepo->getReportStatePairs();
            $badReportStates = array_filter($reportStates, function(string $state) use(&$states) : bool {
                return !array_key_exists($state, $states);
            });
            if (count($badReportStates) !== 0)
            {
                $query->error('report.state', \XF::phrase('svReportImprov_unknown_report_states', ['values' => implode(', ', $badReportStates)]));
            }
            else
            {
                $query->withMetadata('report_state', $reportStates);
                Arr::setUrlConstraint($urlConstraints, 'c.report.state', $reportStates);
            }
        }
        else
        {
            Arr::unsetUrlConstraint($urlConstraints, 'c.report.state');
        }


        $reportTypes = $constraints['c.report.type'];
        if (count($reportTypes) !== 0)
        {
            $types = [];
            foreach ($this->reportRepo->getReportHandlers() as $contentType => $handler)
            {
                if (in_array($contentType, $reportTypes, true) && ($handler instanceof ReportSearchFormInterface))
                {
                    $types[] = $contentType;
                    $handler->applySearchTypeConstraintsFromInput($query, $request, $urlConstraints);
                }
            }

            if (count($types) !== 0)
            {
                $query->withMetadata('report_content_type', $types);
                Arr::setUrlConstraint($urlConstraints, 'c.report.type', $types);
            }
            else
            {
                Arr::unsetUrlConstraint($urlConstraints, 'c.report.type');
            }
        }

        $repo = \SV\SearchImprovements\Globals::repo();

        $repo->applyUserConstraint($query, $constraints, $urlConstraints,
            'c.assigned', 'assigned_user'
        );
        $repo->applyUserConstraint($query, $constraints, $urlConstraints,
            'c.assigner', 'assigner_user'
        );
        $repo->applyUserConstraint($query, $constraints, $urlConstraints,
            'c.warning.user', 'warned_user'
        );
        $repo->applyRangeConstraint($query, $constraints, $urlConstraints,
            'c.replies.lower', 'c.replies.upper', 'replies',
            [$this->getReportQueryTableReference()]
        );
        $repo->applyRangeConstraint($query, $constraints, $urlConstraints,
            'c.warning.points.lower', 'c.warning.points.upper', 'points',
            $this->getWarningLogQueryTableReference(), 'warning_log'
        );
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

    protected function getReportQueryTableReference(): TableReference
    {
        return new TableReference(
            'report',
            'xf_report',
            'report.report_id = search_index.discussion_id'
        );
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

    public function getSearchFormTab(): ?array
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

    public function getSearchFormData(): array
    {
        $form = parent::getSearchFormData();

        $reportRepo = \XF::repository('XF:Report');
        assert($reportRepo instanceof ReportRepo);

        $form['reportStates'] = $reportRepo->getReportStatePairs();
        $form['reportTypes'] = $reportRepo->getReportTypes();
        foreach ($form['reportTypes'] as $rec)
        {
            $handler = $rec['handler'];
            if ($handler instanceof ReportSearchFormInterface)
            {
                $form = array_merge($form, $handler->getSearchFormData());
            }
        }

        return $form;
    }
}