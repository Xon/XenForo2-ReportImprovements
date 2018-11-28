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
     *
     * @return int
     */
    public function getContentDate(Report $report);
}