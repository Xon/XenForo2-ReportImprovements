<?php

namespace SV\ReportImprovements\Report;

use XF\Entity\Report;

/**
 * Interface ContentInterface
 *
 * @package SV\ReportImprovements\Report
 */
interface ContentInterface
{
    /**
     * @param Report|\SV\ReportImprovements\XF\Entity\Report $report
     * @return int|null
     */
    public function getReportedContentDate(Report $report): ?int;
}