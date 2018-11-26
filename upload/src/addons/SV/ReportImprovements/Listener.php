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
     * @param string $rule
     * @param array           $data
     * @param \XF\Entity\User $user
     * @param                 $eventReturnValue
     */
    public static function criteriaUser($rule, array $data, \XF\Entity\User $user, &$eventReturnValue) : void
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
                break;

            case 'sv_reports_maximum':
                if ($reportRepo->countReportsByUser($user, (int) $data['days']) <= $data['reports'])
                {
                    $eventReturnValue = true;
                }
                break;

            case 'sv_reports_minimum_open':
                if ($reportRepo->countReportsByUser($user, (int) $data['days'], 'open') >= $data['reports'])
                {
                    $eventReturnValue = true;
                }
                break;

            case 'sv_reports_maximum_open':
                if ($reportRepo->countReportsByUser($user, (int) $data['days'], 'open') <= $data['reports'])
                {
                    $eventReturnValue = true;
                }
                break;

            case 'sv_reports_minimum_assigned':
                if ($reportRepo->countReportsByUser($user, (int) $data['days'], 'assigned') >= $data['reports'])
                {
                    $eventReturnValue = true;
                }
                break;

            case 'sv_reports_maximum_assigned':
                if ($reportRepo->countReportsByUser($user, (int) $data['days'], 'assigned') <= $data['reports'])
                {
                    $eventReturnValue = true;
                }
                break;

            case 'sv_reports_minimum_resolved':
                if ($reportRepo->countReportsByUser($user, (int) $data['days'], 'resolved') >= $data['reports'])
                {
                    $eventReturnValue = true;
                }
                break;

            case 'sv_reports_maximum_resolved':
                if ($reportRepo->countReportsByUser($user, (int) $data['days'], 'resolved') <= $data['reports'])
                {
                    $eventReturnValue = true;
                }
                break;

            case 'sv_reports_minimum_rejected':
                if ($reportRepo->countReportsByUser($user, (int) $data['days'], 'rejected') >= $data['reports'])
                {
                    $eventReturnValue = true;
                }
                break;

            case 'sv_reports_maximum_rejected':
                if ($reportRepo->countReportsByUser($user, (int) $data['days'], 'rejected') <= $data['reports'])
                {
                    $eventReturnValue = true;
                }
                break;
        }
    }
}