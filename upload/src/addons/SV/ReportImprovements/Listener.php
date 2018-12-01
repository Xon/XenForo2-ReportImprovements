<?php

namespace SV\ReportImprovements;

/**
 * Class Listener
 *
 * @package SV\ReportImprovements
 */
class Listener
{
    /**
     * @param string          $rule
     * @param array           $data
     * @param \XF\Entity\User $user
     * @param bool            $eventReturnValue
     * @return bool
     */
    public static function criteriaUser($rule, array $data, \XF\Entity\User $user, &$eventReturnValue)
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

    /**
     * @param \XF\Pub\App $app
     */
    public static function appPubStartEnd(\XF\Pub\App $app)
    {
        /** @var \SV\ReportImprovements\XF\Entity\User $visitor */
        $visitor = \XF::visitor();
        $session = $app->session();

        if ($visitor->is_moderator || !$visitor->canViewReports())
        {
            return;
        }

        $sessionReportCounts = $session->reportCounts;
        $registryReportCounts = $app->container('reportCounts');

        if ($sessionReportCounts === null
            || ($sessionReportCounts && ($sessionReportCounts['lastBuilt'] < $registryReportCounts['lastModified']))
        )
        {
            /** @var \XF\Repository\Report $reportRepo */
            $reportRepo = $app->repository('XF:Report');

            /** @var \XF\Finder\Report $reportFinder */
            $reportFinder = $app->finder('XF:Report');
            $reports = $reportFinder->isActive()->fetch();
            $reports = $reportRepo->filterViewableReports($reports);

            $total = 0;
            $assigned = 0;

            foreach ($reports AS $reportId => $report)
            {
                $total++;
                if ($report->assigned_user_id === $visitor->user_id)
                {
                    $assigned++;
                }
            }

            $reportCounts = [
                'total' => $total,
                'assigned' => $assigned,
                'lastBuilt' => $registryReportCounts['lastModified']
            ];

            $session->reportCounts = $reportCounts;
        }
    }
}