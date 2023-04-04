<?php

namespace SV\ReportImprovements\Search\Data;

use SV\ReportImprovements\XF\Repository\Report as ReportRepo;

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
     * @param \XF\Search\Search $searcher
     */
    public function __construct($contentType, \XF\Search\Search $searcher)
    {
        /** @noinspection PhpMultipleClassDeclarationsInspection */
        parent::__construct($contentType, $searcher);

        $this->reportRepo = \XF::repository('XF:Report');
        $this->searchRepo = \XF::repository('SV\SearchImprovements:Search');
        $this->isAddonFullyActive = $this->reportRepo instanceof ReportRepo;
        $this->isUsingElasticSearch = \SV\SearchImprovements\Globals::repo()->isUsingElasticSearch();
    }
}