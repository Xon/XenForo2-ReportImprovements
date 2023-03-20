<?php
/*
 * This file is part of a XenForo add-on.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SV\ReportImprovements\Listener;

abstract class appStart
{
    private function __construct() { }

    public static function appPubStartEnd(\XF\Pub\App $app)
    {
        /** @var \SV\ReportImprovements\XF\Entity\User $visitor */
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
            /** @var \SV\ReportImprovements\XF\Repository\Report $reportRepo */
            $reportRepo = $app->repository('XF:Report');
            $session['reportCounts'] = $reportRepo->rebuildSessionReportCounts($registryReportCounts);
        }
    }
}