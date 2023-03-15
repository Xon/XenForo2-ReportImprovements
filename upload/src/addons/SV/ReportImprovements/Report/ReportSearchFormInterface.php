<?php

namespace SV\ReportImprovements\Report;

use XF\Search\MetadataStructure;

interface ReportSearchFormInterface
{
    public function getSearchFormTemplate(): string;
    public function getSearchFormData(): array;
    public function applySearchTypeConstraintsFromInput(\XF\Search\Query\Query $query, \XF\Http\Request $request, array $urlConstraints): void;

    public function populateMetaData(\XF\Entity\Report $entity, array &$metaData): void;
    public function setupMetadataStructure(MetadataStructure $structure): void;
}