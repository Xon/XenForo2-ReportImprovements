<?php

namespace SV\ReportImprovements\Listener;

use SV\ReportImprovements\XF\Repository\Report;
use XF\Pub\App;

abstract class appStart
{
    private function __construct() { }

    public static function appPubStartEnd(App $app)
    {
        // Don't assert the type hint here.
        // $visitor can be very briefly not extended but this function called when the add-on is being upgraded due to is_processing behavior
        $visitor = \XF::visitor();
        $session = $app->session();

        if ($visitor->is_moderator ||
            !$session ||
            !\is_callable([$visitor,'canViewReports']) || !$visitor->canViewReports())
        {
            return;
        }

        $sessionReportCounts = $session['reportCounts'];
        $registryReportCounts = $app->container('reportCounts');

        if ($sessionReportCounts === null
            || ($sessionReportCounts && ($sessionReportCounts['lastBuilt'] < $registryReportCounts['lastModified']))
        )
        {
            /** @var Report $reportRepo */
            $reportRepo = \SV\StandardLib\Helper::repository(\XF\Repository\Report::class);
            $session['reportCounts'] = $reportRepo->rebuildSessionReportCounts($registryReportCounts);
        }
    }
}