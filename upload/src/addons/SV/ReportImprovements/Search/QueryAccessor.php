<?php

namespace SV\ReportImprovements\Search;

use XF\Search\Query\MetadataConstraint;
use XF\Search\Query\Query;

abstract class QueryAccessor extends Query
{
    /** @noinspection PhpMissingParentConstructorInspection */
    private function __construct() {}

    /**
     * @param Query $query
     * @param MetadataConstraint[] $metadataConstraints
     * @return void
     */
    public static function setMetadataConstraints(Query $query, array $metadataConstraints): void
    {
        $query->metadataConstraints = $metadataConstraints;
    }
}