<?php

namespace SV\ReportImprovements\Job\Upgrades;

use SV\ReportImprovements\Entity\WarningLog;
use XF\Job\AbstractRebuildJob;
use XF\Phrase;

/**
 * Class Upgrade1090200Step1
 *
 * @package SV\ReportImprovements\Job\Upgrades
 */
class Upgrade1680614325Step3 extends AbstractRebuildJob
{
    /**
     * @param int $start
     * @param int $batch
     * @return array
     */
    protected function getNextIds($start, $batch)
    {
        $db = $this->app->db();

        return $db->fetchAllColumn($db->limit(
            '
            SELECT warnLog.warning_log_id
            FROM xf_sv_warning_log AS warnLog
            JOIN xf_report_comment xrc ON warnLog.warning_log_id = xrc.warning_log_id
            WHERE warnLog.warning_log_id > ?
            ORDER BY warnLog.warning_log_id
			', $batch
        ), $start);
    }

    /**
     * @param $id
     * @throws \Exception
     */
    protected function rebuildById($id)
    {
        $warningLog = \XF::app()->find('SV\ReportImprovements:WarningLog', $id);
        assert($warningLog instanceof WarningLog);
        $warningLog->rebuildLatestVersionFlag(false);
    }

    /**
     * @return Phrase
     */
    protected function getStatusType()
    {
        return \XF::phrase('warning_log');
    }
}