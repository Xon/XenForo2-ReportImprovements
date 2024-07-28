<?php

namespace SV\ReportImprovements\Job;

use SV\ReportImprovements\Entity\WarningLog;
use XF\Job\AbstractRebuildJob;
use XF\Phrase;
use function array_merge;

/**
 * Class Upgrade1090200Step1
 *
 * @package SV\ReportImprovements\Job\Upgrades
 */
class RebuildWarningLogLatestVersion extends AbstractRebuildJob
{
    protected $optionData = [
        'reindex' => false,
    ];

    protected function setupData(array $data)
    {
        $this->defaultData = array_merge($this->optionData, $this->defaultData);

        return parent::setupData($data);
    }

    /**
     * @param int $start
     * @param int $batch
     * @return array
     */
    protected function getNextIds($start, $batch)
    {
        $db = \XF::app()->db();

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
        $warningLog = \SV\StandardLib\Helper::find(\SV\ReportImprovements\Entity\WarningLog::class, $id);
        assert($warningLog instanceof WarningLog);
        $warningLog->rebuildLatestVersionFlag($this->data['reindex']);
    }

    /**
     * @return Phrase
     */
    protected function getStatusType()
    {
        return \XF::phrase('warning_log');
    }
}