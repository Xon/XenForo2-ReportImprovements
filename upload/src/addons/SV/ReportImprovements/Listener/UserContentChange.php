<?php

namespace SV\ReportImprovements\Listener;

use XF\Service\User\ContentChange;
use function is_array;

abstract class UserContentChange
{
    private function __construct() { }

    /** @noinspection PhpUnusedParameterInspection */
    public static function userContentChangeInit(ContentChange $changeService, array &$updates): void
    {
        $updates['xf_report'][] = ['assigner_user_id'];

        if (is_array($updates['xf_report_comment']) && is_array($updates['xf_report_comment'][0]))
        {
            $updates['xf_report_comment'] = [$updates['xf_report_comment']];
        }
        $updates['xf_report_comment'][] = ['assigned_user_id', 'assigned_username'];
        $updates['xf_report_comment'][] = ['last_edit_user_id'];

        if (!isset($updates['xf_sv_warning_log']))
        {
            $updates['xf_sv_warning_log'] = [];
        }

        $updates['xf_sv_warning_log'][] = ['user_id'];
        $updates['xf_sv_warning_log'][] = ['warning_user_id'];
    }
}