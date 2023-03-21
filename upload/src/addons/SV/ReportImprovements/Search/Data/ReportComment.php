<?php

namespace SV\ReportImprovements\Search\Data;

use SV\ReportImprovements\Globals;
use SV\ReportImprovements\Report\ReportSearchFormInterface;
use SV\ReportImprovements\Search\QueryAccessor;
use SV\ReportImprovements\XF\Repository\Report as ReportRepo;
use SV\ReportImprovements\XF\Entity\ReportComment as ReportCommentEntity;
use SV\SearchImprovements\Search\DiscussionTrait;
use SV\SearchImprovements\Util\Arr;
use SV\SearchImprovements\XF\Search\Query\Constraints\AndConstraint;
use SV\SearchImprovements\XF\Search\Query\Constraints\OrConstraint;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Entity;
use XF\Search\Data\AbstractData;
use XF\Search\IndexRecord;
use XF\Search\MetadataStructure;
use XF\Search\Query\MetadataConstraint;
use XF\Search\Query\Query;
use XF\Search\Query\TableReference;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_merge;
use function array_unique;
use function assert;
use function count;
use function in_array;
use function is_array;
use function reset;

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

    /** @var ReportRepo */
    protected $reportRepo;

    public function __construct($contentType, \XF\Search\Search $searcher)
    {
        parent::__construct($contentType, $searcher);

        $this->reportRepo = \XF::repository('XF:Report');
    }

    public function canViewContent(Entity $entity, &$error = null): bool
    {
        assert($entity instanceof ReportCommentEntity);
        $report = $entity->Report;
        return $report && $report->canView();
    }

    /**
     * @param int|int[] $id
     * @param bool $forView
     * @return AbstractCollection|array|Entity|null
     */
    public function getContent($id, $forView = false)
    {
        $reportRepo = \XF::repository('XF:Report');
        if (!($reportRepo instanceof ReportRepo))
        {
            // This function may be invoked when the add-on is disabled, just return nothing
            return is_array($id) ? [] : null;
        }

        $entities = parent::getContent($id, $forView);

        if ($entities instanceof AbstractCollection)
        {
            /** @var ReportRepo $reportRepo */
            $reportRepo = \XF::repository('XF:Report');
            $reportRepo->svPreloadReportComments($entities);
        }

        return $entities;
    }

    /**
     * @param int $lastId
     * @param int $amount
     * @param bool $forView
     * @return AbstractCollection
     */
    public function getContentInRange($lastId, $amount, $forView = false): AbstractCollection
    {
        $reportRepo = \XF::repository('XF:Report');
        if (!($reportRepo instanceof ReportRepo))
        {
            // This function may be invoked when the add-on is disabled, just return nothing
            return new ArrayCollection([]);
        }

        $contents = parent::getContentInRange($lastId, $amount, $forView);

        $reportRepo->svPreloadReportComments($contents);

        return $contents;
    }

    public function getEntityWith($forView = false): array
    {
        $get = ['Report', 'User', 'WarningLog'];

        if ($forView)
        {
            $visitor = \XF::visitor();
            $get[] = 'Report.Permissions|' . $visitor->permission_combination_id;
        }

        return $get;
    }

    public function getResultDate(Entity $entity)
    {
        assert($entity instanceof ReportCommentEntity);
        return $entity->comment_date;
    }

    public function getIndexData(Entity $entity): ?IndexRecord
    {
        if (!($entity instanceof ReportCommentEntity))
        {
            // This function may be invoked when the add-on is disabled, just return nothing to index
            return null;
        }

        $warningLog = $entity->WarningLog;
        if ($warningLog !== null)
        {
            return $this->searcher->handler('warning')->getIndexData($warningLog);
        }

        /** @var \SV\ReportImprovements\XF\Entity\Report $report */
        $report = $entity->Report;
        if ($report === null)
        {
            return null;
        }

        return IndexRecord::create('report_comment', $entity->report_comment_id, [
            'title'         => $report->title_string,
            'message'       => $this->getMessage($entity->message),
            'date'          => $entity->comment_date,
            'user_id'       => $entity->user_id,
            'discussion_id' => $entity->report_id,
            'metadata'      => $this->getMetaData($entity),
        ]);
    }

    protected function getMessage(ReportCommentEntity $entity): string
    {
        $message = $entity->message;

        if ($entity->alertComment !== null)
        {
            $message .= "\n".$entity->alertComment;
        }

        return $message;
    }

    protected function getMetaData(ReportCommentEntity $entity): array
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

        $reportHandler = $this->reportRepo->getReportHandler($report->content_type, null);
        if ($reportHandler instanceof ReportSearchFormInterface)
        {
            $reportHandler->populateMetaData($report, $metaData);
        }

        $this->populateDiscussionMetaData($entity, $metaData);

        return $metaData;
    }

    public function getTemplateData(Entity $entity, array $options = [])
    {
        assert($entity instanceof ReportCommentEntity);
        return [
            'report'        => $entity->Report,
            'reportComment' => $entity,
            'options'       => $options,
        ];
    }

    public function getSearchableContentTypes(): array
    {
        return ['report_comment', 'warning', 'report'];
    }

    public function getGroupByType(): string
    {
        return 'report';
    }

    public function setupMetadataStructure(MetadataStructure $structure)
    {
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

        $this->setupDiscussionMetadataStructure($structure);
    }

    public function applyTypeConstraintsFromInput(Query $query, \XF\Http\Request $request, array &$urlConstraints): void
    {
        $isUsingElasticSearch = \SV\SearchImprovements\Globals::repo()->isUsingElasticSearch();
        $constraints = $request->filter([
            'c.assigned'         => 'str',
            'c.assigner'         => 'str',
            'c.participants'     => 'str',

            'c.replies.lower'       => 'uint',
            'c.replies.upper'       => '?uint',

            'c.report.type'         => 'array-str',
            'c.report.state'        => 'array-str',
            'c.report.contents'     => 'bool',
            'c.report.comments'     => 'bool',
            'c.report.user_reports' => 'bool',
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
        assert(is_array($reportTypes));
        if (count($reportTypes) !== 0)
        {
            // MySQL backend doesn't support composing multiple queries atm
            if (!$isUsingElasticSearch && count($reportTypes) > 1)
            {
                $query->error('c.report.type', \XF::phrase('svReportImprov_only_single_report_type_permitted'));
                $reportTypes = [];
            }

            $types = [];
            $handlers = $this->reportRepo->getReportHandlers();
            foreach ($reportTypes as $reportType)
            {
                $handler = $handlers[$reportType] ?? null;
                if ($handler instanceof ReportSearchFormInterface)
                {
                    $oldConstraints = $query->getMetadataConstraints();
                    QueryAccessor::setMetadataConstraints($query, []);

                    $handler->applySearchTypeConstraintsFromInput($query, $request, $urlConstraints);
                    $types[$reportType] = $query->getMetadataConstraints();

                    QueryAccessor::setMetadataConstraints($query, $oldConstraints);
                }
                else
                {
                    $types[$reportType] = [];
                }
            }

            if (count($types) !== 0)
            {
                if (count($types) > 1)
                {
                    $constraints = [];
                    foreach ($types as $contentType => $nestedConstraints)
                    {
                        $constraints[] = new AndConstraint(
                            new MetadataConstraint('report_content_type', $contentType),
                            ...$nestedConstraints
                        );
                    }
                    $query->withMetadata(new OrConstraint(...$constraints));
                }
                else
                {
                    $constraint = reset($types);
                    $query->withMetadata('report_content_type', array_keys($types));
                    if (count($constraint) !== 0)
                    {
                        $query->withMetadata($constraint);
                    }
                }

                Arr::setUrlConstraint($urlConstraints, 'c.report.type', array_keys($types));
            }
            else
            {
                Arr::unsetUrlConstraint($urlConstraints, 'c.report.type');
            }
        }

        $repo = \SV\SearchImprovements\Globals::repo();

        $repo->applyUserConstraint($query, $constraints, $urlConstraints,
            'c.report_user', 'report_user'
        );
        $repo->applyUserConstraint($query, $constraints, $urlConstraints,
            'c.assigned', 'assigned_user'
        );
        $repo->applyUserConstraint($query, $constraints, $urlConstraints,
            'c.assigner', 'assigner_user'
        );
        $repo->applyUserConstraint($query, $constraints, $urlConstraints,
            'c.participants', 'discussion_user'
        );
        $repo->applyRangeConstraint($query, $constraints, $urlConstraints,
            'c.replies.lower', 'c.replies.upper', 'replies',
            [$this->getReportQueryTableReference()]
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
    public function getTypePermissionConstraints(Query $query, $isOnlyType): array
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
    protected function getWarningLogQueryTableReference(): array
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
        $visitor = \XF::visitor();
        if (!($visitor instanceof \SV\ReportImprovements\XF\Entity\User))
        {
            // This function may be invoked when the add-on is disabled, just return nothing to show
            return null;
        }

        if (!$visitor->canReportSearch())
        {
            return null;
        }

        return [
            'title' => \XF::phrase('svReportImprov_search.reports'),
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