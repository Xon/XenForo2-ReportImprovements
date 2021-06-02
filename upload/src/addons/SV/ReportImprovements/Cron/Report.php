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
        $options = \XF::options();
        $daysLimit = (int)($options->svReportImpro_autoExpireDays ?? 0);
        $expireAction = $options->svReportImpro_autoExpireAction ?? '';
        if ($daysLimit <= 0 || !$expireAction)
        {
            return;
        }

        \XF::app()->jobManager()->enqueueUnique(
            'resolveInactiveReports',
            'SV\ReportImprovements:ResolveInactiveReport',
            []
        );
    }
}