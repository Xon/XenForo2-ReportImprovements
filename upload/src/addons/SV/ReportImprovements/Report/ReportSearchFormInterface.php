<?php

namespace SV\ReportImprovements\Report;

use XF\Entity\Report as ReportEntity;
use XF\Http\Request;
use XF\Search\MetadataStructure;
use XF\Search\Query\Query;

interface ReportSearchFormInterface
{
    public function getSearchFormTemplate(): string;
    public function getSearchFormData(): array;
    public function applySearchTypeConstraintsFromInput(Query $query, Request $request, array $urlConstraints): void;

    public function populateMetaData(ReportEntity $entity, array &$metaData): void;
    public function setupMetadataStructure(MetadataStructure $structure): void;
}