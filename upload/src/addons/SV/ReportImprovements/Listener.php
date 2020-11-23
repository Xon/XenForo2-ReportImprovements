<?php

namespace SV\ReportImprovements;

/**
 * Class Listener
 *
 * @package SV\ReportImprovements
 */
class Listener
{
    public static function criteriaUser(string $rule, array $data, \XF\Entity\User $user, bool &$eventReturnValue): bool
    {
        /** @var \SV\ReportImprovements\XF\Repository\Report $reportRepo */
        $reportRepo = \XF::repository('XF:Report');

        switch ($rule)
        {
            case 'sv_reports_minimum':
                if ($reportRepo->countReportsByUser($user, (int) $data['days']) >= $data['reports'])
                {
                    $eventReturnValue = true;
                }
                return false;

            case 'sv_reports_maximum':
                if ($reportRepo->countReportsByUser($user, (int) $data['days']) <= $data['reports'])
                {
                    $eventReturnValue = true;
                }
                return false;

            case 'sv_reports_minimum_open':
                if ($reportRepo->countReportsByUser($user, (int) $data['days'], 'open') >= $data['reports'])
                {
                    $eventReturnValue = true;
                }
                return false;

            case 'sv_reports_maximum_open':
                if ($reportRepo->countReportsByUser($user, (int) $data['days'], 'open') <= $data['reports'])
                {
                    $eventReturnValue = true;
                }
                return false;

            case 'sv_reports_minimum_assigned':
                if ($reportRepo->countReportsByUser($user, (int) $data['days'], 'assigned') >= $data['reports'])
                {
                    $eventReturnValue = true;
                }
                return false;

            case 'sv_reports_maximum_assigned':
                if ($reportRepo->countReportsByUser($user, (int) $data['days'], 'assigned') <= $data['reports'])
                {
                    $eventReturnValue = true;
                }
                return false;

            case 'sv_reports_minimum_resolved':
                if ($reportRepo->countReportsByUser($user, (int) $data['days'], 'resolved') >= $data['reports'])
                {
                    $eventReturnValue = true;
                }
                return false;

            case 'sv_reports_maximum_resolved':
                if ($reportRepo->countReportsByUser($user, (int) $data['days'], 'resolved') <= $data['reports'])
                {
                    $eventReturnValue = true;
                }
                return false;

            case 'sv_reports_minimum_rejected':
                if ($reportRepo->countReportsByUser($user, (int) $data['days'], 'rejected') >= $data['reports'])
                {
                    $eventReturnValue = true;
                }
                return false;

            case 'sv_reports_maximum_rejected':
                if ($reportRepo->countReportsByUser($user, (int) $data['days'], 'rejected') <= $data['reports'])
                {
                    $eventReturnValue = true;
                }
                return false;

            default:
                return true;
        }
    }

    public static function appPubStartEnd(\XF\Pub\App $app)
    {
        /** @var \SV\ReportImprovements\XF\Entity\User $visitor */
        $visitor = \XF::visitor();
        $session = $app->session();

        if ($visitor->is_moderator ||
            !$session ||
            !is_callable([$visitor,'canViewReports']) || !$visitor->canViewReports())
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