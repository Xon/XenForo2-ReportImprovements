<?php

namespace SV\ReportImprovements\Cron;

use SV\ReportImprovements\Job\ResolveInactiveReport;

/**
 * Class Report
 *
 * @package SV\ReportImprovements\Cron
 */
class Report
{
    public static function resolveInactiveReports()
    {
        $options = \XF::options();
        $daysLimit = (int)($options->svReportImpro_autoExpireDays ?? 0);
        $expireAction = $options->svReportImpro_autoExpireAction ?? '';
        if ($daysLimit <= 0 || \strlen($expireAction) === 0)
        {
            return;
        }

        \XF::app()->jobManager()->enqueueUnique(
            'resolveInactiveReports',
            ResolveInactiveReport::class,
            []
        );
    }
}