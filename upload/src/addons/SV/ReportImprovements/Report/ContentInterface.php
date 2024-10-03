<?php

namespace SV\ReportImprovements\Report;

use SV\ReportImprovements\XF\Entity\Report as ExtendedReportEntity;
use XF\Entity\Report as ReportEntity;

interface ContentInterface
{
    /**
     * @param ReportEntity|ExtendedReportEntity $report
     * @return int|null
     */
    public function getReportedContentDate(ReportEntity $report): ?int;
}