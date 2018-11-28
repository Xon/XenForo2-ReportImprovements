<?php

namespace SV\ReportImprovements\Cron;

/**
 * Class Report
 *
 * @package SV\ReportImprovements\Cron
 */
class Report
{
    public static function resolveInactiveReports()
    {
        \XF::app()->jobManager()->enqueueUnique(
            'resolveInactiveReports',
            'SV\ReportImprovements:ResolveInactiveReport',
            []
        );
    }
}