<?php

namespace SV\ReportImprovements\Search\Data;

use SV\ElasticSearchEssentials\XF\Repository\ImpossibleSearchResultsException;
use SV\ReportImprovements\Enums\ReportType;
use SV\ReportImprovements\XF\Repository\Report as ReportRepo;
use SV\SearchImprovements\Globals;
use SV\SearchImprovements\XF\Search\Query\Constraints\PermissionConstraint;
use SV\SearchImprovements\XF\Search\Query\Constraints\TypeConstraint;
use XF\Search\Query\MetadataConstraint;
use XF\Search\Query\Query;
use XF\Search\Search;

trait SearchDataSetupTrait
{
    /** @var ReportRepo|\XF\Repository\Report */
    protected $reportRepo;
    /** @var \SV\SearchImprovements\Repository\Search */
    protected $searchRepo;
    /** @var bool */
    protected $isAddonFullyActive;
    /** @var bool */
    protected $isUsingElasticSearch;

    /**
     * @param string            $contentType
     * @param Search $searcher
     */
    public function __construct($contentType, Search $searcher)
    {
        /** @noinspection PhpMultipleClassDeclarationsInspection */
        parent::__construct($contentType, $searcher);

        $this->reportRepo = \XF::repository('XF:Report');
        $this->searchRepo = \XF::repository('SV\SearchImprovements:Search');
        $this->isAddonFullyActive = $this->reportRepo instanceof ReportRepo;
        $this->isUsingElasticSearch = Globals::repo()->isUsingElasticSearch();
    }

    /**
     * @param Query $query
     * @param bool  $isOnlyType
     * @return PermissionConstraint[]|MetadataConstraint[]
     * @throws ImpossibleSearchResultsException
     * @noinspection PhpUnusedParameterInspection
     */
    public function getImpossibleTypePermissionConstraints(Query $query, bool $isOnlyType): array
    {
        if (\XF::isAddOnActive('SV/ElasticSearchEssentials'))
        {
            throw new ImpossibleSearchResultsException();
        }
        else if ($this->isUsingElasticSearch)
        {
            // XF constraints are AND'ed together for positive queries (ANY/ALL), and OR'ed for all negative queries (NONE).
            // PermissionConstraint forces the sub-query as a negative query instead of being part of the AND'ed positive queries
            return [
                new PermissionConstraint(new TypeConstraint(...$this->getSearchableContentTypes()))
            ];
        }
        else // mysql
        {
            return [
                new MetadataConstraint('report_type', ReportType::get(), MetadataConstraint::MATCH_NONE)
            ];
        }
    }
}