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
}