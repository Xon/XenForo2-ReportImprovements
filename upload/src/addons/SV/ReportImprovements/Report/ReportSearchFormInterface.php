<?php

namespace SV\ReportImprovements\Report;

interface ReportSearchFormInterface
{
    public function getSearchFormTemplate(): string;
    public function getSearchFormData(): array;
    public function applySearchTypeConstraintsFromInput(\XF\Search\Query\Query $query, \XF\Http\Request $request, array $urlConstraints): void;
}