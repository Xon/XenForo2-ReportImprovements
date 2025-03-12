<?php

namespace SV\ReportImprovements\Cron;

use SV\ReportImprovements\Job\ResolveInactiveReport;

class Report
{
    public static function resolveInactiveReports()
    {
        $options = \XF::options();
        $daysLimit = $options->svReportImpro_autoExpireDays ?? 0;
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