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
        $daysLimit = isset($options->svReportImpro_autoExpireDays) ? (int)$options->svReportImpro_autoExpireDays : 0;
        $expireAction = isset($options->svReportImpro_autoExpireAction) ? $options->svReportImpro_autoExpireAction : '';
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