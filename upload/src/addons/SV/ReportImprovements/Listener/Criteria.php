<?php

namespace SV\ReportImprovements\Listener;

use SV\ReportImprovements\XF\Repository\Report as ExtendedReportRepo;
use SV\StandardLib\Helper;
use XF\Entity\User as UserEntity;
use XF\Repository\Report as ReportRepo;

abstract class Criteria
{
    private function __construct() { }

    public static function criteriaUser(string $rule, array $data, UserEntity $user, bool &$eventReturnValue): bool
    {
        /** @var ExtendedReportRepo $reportRepo */
        $reportRepo = Helper::repository(ReportRepo::class);

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