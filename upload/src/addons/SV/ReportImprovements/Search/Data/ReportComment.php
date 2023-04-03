<?php

namespace SV\ReportImprovements\Search\Data;

use SV\ReportImprovements\Enums\ReportType;
use SV\ReportImprovements\Globals;
use SV\ReportImprovements\Report\ReportSearchFormInterface;
use SV\ReportImprovements\XF\Repository\Report as ReportRepo;
use SV\ReportImprovements\XF\Entity\ReportComment as ReportCommentEntity;
use SV\SearchImprovements\Search\DiscussionTrait;
use SV\SearchImprovements\Search\Features\SearchOrder;
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
use function is_string;
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

    /** @var ReportRepo|\XF\Repository\Report */
    protected $reportRepo;
    /** @var bool */
    protected $isAddonFullyActive;
    /** @var bool */
    protected $isUsingElasticSearch;

    /**
     * @param string            $contentType
     * @param \XF\Search\Search $searcher
     */
    public function __construct($contentType, \XF\Search\Search $searcher)
    {
        parent::__construct($contentType, $searcher);

        $this->reportRepo = \XF::repository('XF:Report');
        $this->isAddonFullyActive = $this->reportRepo instanceof ReportRepo;
        $this->isUsingElasticSearch = \SV\SearchImprovements\Globals::repo()->isUsingElasticSearch();
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
        if (!$this->isAddonFullyActive)
        {
            // This function may be invoked when the add-on is disabled, just return nothing
            return is_array($id) ? [] : null;
        }

        $entities = parent::getContent($id, $forView);

        if ($entities instanceof AbstractCollection)
        {
            $this->reportRepo->svPreloadReportComments($entities);
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
        if (!$this->isAddonFullyActive)
        {
            // This function may be invoked when the add-on is disabled, just return nothing
            return new ArrayCollection([]);
        }

        $contents = parent::getContentInRange($lastId, $amount, $forView);

        $this->reportRepo->svPreloadReportComments($contents);

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

    public function getResultDate(Entity $entity): int
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
            return $this->searcher->handler('warning')->getIndexData($entity);
        }

        /** @var \SV\ReportImprovements\XF\Entity\Report $report */
        $report = $entity->Report;
        if ($report === null)
        {
            return null;
        }

        return IndexRecord::create('report_comment', $entity->report_comment_id, [
            'title'         => '',
            'message'       => $this->getMessage($entity),
            'date'          => $entity->comment_date,
            'user_id'       => $entity->user_id,
            'discussion_id' => $entity->report_id,
            'metadata'      => $this->getMetaData($entity),
        ]);
    }

    protected function getMessage(ReportCommentEntity $entity): string
    {
        /** @noinspection PhpUnnecessaryLocalVariableInspection */
        $message = $entity->message;

        // currently not required:
        //$message .= $this->getEntityToMessage($entity);

        return $message;
    }

    protected function getEntityToMessage(Entity $entity): string
    {
        $message = '';
        foreach ($entity->structure()->columns as $column => $schema)
        {
            if (
                ($schema['type'] ?? '') !== Entity::STR ||
                empty($schema['allowedValues']) || // aka enums
                ($schema['noIndex'] ?? false)
            )
            {
                continue;
            }

            $value = $entity->get($column);
            if ($value === null || $value === '')
            {
                continue;
            }

            $message .= "\n" . $value;
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
            'report_type'         => $entity->getReportType(),
            'content_user'        => $report->content_user_id, // duplicate of report.user_id
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

        $this->populateDiscussionMetaData($report, $metaData);

        return $metaData;
    }

    public function getTemplateData(Entity $entity, array $options = []): array
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
        return ['report_comment', 'report'];
    }

    public function getGroupByType(): string
    {
        return 'report';
    }

    public function setupMetadataStructure(MetadataStructure $structure): void
    {
        // shared with Report
        foreach ($this->reportRepo->getReportHandlers() as $handler)
        {
            if ($handler instanceof ReportSearchFormInterface)
            {
                $handler->setupMetadataStructure($structure);
            }
        }
        $structure->addField('report_type', MetadataStructure::KEYWORD);
        $structure->addField('report', MetadataStructure::INT);
        $structure->addField('state_change', MetadataStructure::KEYWORD);
        $structure->addField('report_state', MetadataStructure::KEYWORD);
        $structure->addField('report_content_type', MetadataStructure::KEYWORD);
        $structure->addField('content_user', MetadataStructure::INT);
        $structure->addField('assigned_user', MetadataStructure::INT);
        $structure->addField('assigner_user', MetadataStructure::INT);

        $this->setupDiscussionMetadataStructure($structure);
    }

    public function applyTypeConstraintsFromInput(Query $query, \XF\Http\Request $request, array &$urlConstraints): void
    {
        $constraints = $request->filter([
            'c.assigned'     => 'str',
            'c.assigner'     => 'str',
            'c.participants' => 'str',

            'c.replies.lower' => 'uint',
            'c.replies.upper' => '?uint,empty-str-to-null',

            'c.report.user' => 'str',
            'c.report.type'    => 'array-str',
            'c.report.content' => 'array-str',
            'c.report.state'   => 'array-str',
        ]);

        $rawReportTypes = $constraints['c.report.type'];
        assert(is_array($rawReportTypes));
        if (count($rawReportTypes) !== 0)
        {
            $reportTypes = [];
            $types = ReportType::get();
            foreach ($rawReportTypes as $value)
            {
                if (in_array($value, $types, true))
                {
                    $reportTypes[] = $value;
                }
            }
            if (count($reportTypes) !== 0  && count($reportTypes) < count($types))
            {
                if (in_array(ReportType::Reply_ban, $reportTypes, true) || in_array(ReportType::Warning, $reportTypes, true))
                {
                    $types = $query->getTypes() ?? [];
                    if (count($types) !== 0 && !in_array('warning', $types, true))
                    {
                        $types[] = 'warning';
                        $query->inTypes($types);
                    }
                }
                $query->withMetadata('report_type', $reportTypes);
                Arr::setUrlConstraint($urlConstraints, 'c.report.type', $reportTypes);
            }
            else
            {
                Arr::unsetUrlConstraint($urlConstraints, 'c.report.type');
            }
        }
        else
        {
            Arr::unsetUrlConstraint($urlConstraints, 'c.report.type');
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
            else if (count($reportStates) !== count($states))
            {
                $query->withMetadata('report_state', $reportStates);
                Arr::setUrlConstraint($urlConstraints, 'c.report.state', $reportStates);
            }
            else
            {
                Arr::unsetUrlConstraint($urlConstraints, 'c.report.state');
            }
        }
        else
        {
            Arr::unsetUrlConstraint($urlConstraints, 'c.report.state');
        }

        $reportContentTypes = $constraints['c.report.content'];
        assert(is_array($reportContentTypes));
        if (count($reportContentTypes) !== 0)
        {
            // MySQL backend doesn't support composing multiple queries atm
            if (!$this->isUsingElasticSearch && count($reportContentTypes) > 1)
            {
                $query->error('c.report.content', \XF::phrase('svReportImprov_only_single_report_type_permitted'));
                $reportContentTypes = [];
            }

            $types = [];
            $handlers = $this->reportRepo->getReportHandlers();
            foreach ($reportContentTypes as $reportContentType)
            {
                $handler = $handlers[$reportContentType] ?? null;
                if ($handler instanceof ReportSearchFormInterface)
                {
                    $tmpQuery = \XF::app()->search()->getQuery();
                    $handler->applySearchTypeConstraintsFromInput($tmpQuery, $request, $urlConstraints);
                    $types[$reportContentType] = $tmpQuery->getMetadataConstraints();
                }
                else
                {
                    $types[$reportContentType] = [];
                }
            }

            if (count($types) !== 0)
            {
                if (count($types) > 1)
                {
                    $queryConstraints = [];
                    foreach ($types as $contentType => $nestedConstraints)
                    {
                        $queryConstraints[] = new AndConstraint(
                            new MetadataConstraint('report_content_type', $contentType),
                            ...$nestedConstraints
                        );
                    }
                    $query->withMetadata(new OrConstraint(...$queryConstraints));
                }
                else
                {
                    $queryConstraints = reset($types);
                    $query->withMetadata('report_content_type', array_keys($types));
                    foreach ($queryConstraints as $queryConstraint)
                    {
                        $query->withMetadata($queryConstraint);
                    }
                }

                if (count($types) !== count($handlers))
                {
                    Arr::setUrlConstraint($urlConstraints, 'c.report.content', array_keys($types));
                }
                else
                {
                    Arr::unsetUrlConstraint($urlConstraints, 'c.report.content');
                }
            }
            else
            {
                Arr::unsetUrlConstraint($urlConstraints, 'c.report.content');
            }
        }

        $repo = \SV\SearchImprovements\Globals::repo();

        $repo->applyUserConstraint($query, $constraints, $urlConstraints,
            'c.report.user', 'content_user'
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

     * @param Query $query
     * @param bool  $isOnlyType
     * @return MetadataConstraint[]
     */
    public function getTypePermissionConstraints(Query $query, $isOnlyType): array
    {
        if (!Globals::$reportInAccountPostings)
        {
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
                new MetadataConstraint('report_type', [ReportType::User_report], 'none'),
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

    /**
     * @param string $order
     * @return string|SearchOrder|\XF\Search\Query\SqlOrder|null
     */
    public function getTypeOrder($order)
    {
        assert(is_string($order));
        if (array_key_exists($order, $this->getSortOrders()))
        {
            return new SearchOrder([$order, 'date']);
        }

        return parent::getTypeOrder($order);
    }

    protected function getSortOrders(): array
    {
        if (!$this->isUsingElasticSearch)
        {
            return [];
        }

        return [
            'replies' =>  \XF::phrase('svReportImpov_sort_order.comment_count'),
        ];
    }

    public function getSearchFormData(): array
    {
        assert($this->isAddonFullyActive);
        $form = parent::getSearchFormData();

        $form['sortOrders'] = $this->getSortOrders();
        $form['reportStates'] = $this->reportRepo->getReportStatePairs();
        $form['reportHandlers'] = $this->reportRepo->getReportHandlers();
        $form['reportTypes'] = ReportType::getPairs();
        foreach ($form['reportHandlers'] as $handler)
        {
            if ($handler instanceof ReportSearchFormInterface)
            {
                $form = array_merge($form, $handler->getSearchFormData());
            }
        }

        return $form;
    }
}