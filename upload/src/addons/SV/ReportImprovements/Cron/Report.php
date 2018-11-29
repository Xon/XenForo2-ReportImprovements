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
        $daysLimit = (int) $options->sv_ri_expiry_days;
        if ($daysLimit <= 0 || !$options->sv_ri_expiry_action)
        {
            return ;
        }

        \XF::app()->jobManager()->enqueueUnique(
            'resolveInactiveReports',
            'SV\ReportImprovements:ResolveInactiveReport',
            []
        );
    }
}