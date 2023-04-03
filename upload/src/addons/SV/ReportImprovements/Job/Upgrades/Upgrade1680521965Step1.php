<?php

namespace SV\ReportImprovements\Job\Upgrades;

use XF\Job\AbstractRebuildJob;

/**
 * Class Upgrade1090200Step1
 *
 * @package SV\ReportImprovements\Job\Upgrades
 */
class Upgrade1680521965Step1 extends AbstractRebuildJob
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
            SELECT report_id
            FROM xf_report_comment 
            WHERE report_id > ?
            ORDER BY report_id
			', $batch
        ), $start);
    }

    /**
     * @param $id
     * @throws \Exception
     */
    protected function rebuildById($id)
    {
        // note: this upgrade required a search rebuild so no point doing that here
        $this->app->db()->query("
            UPDATE xf_report
            SET comment_count = (
                SELECT COUNT(*) 
                FROM xf_report_comment AS comment
                WHERE xf_report.report_id = comment.report_id
                      AND (comment.message <> '' OR (comment.warning_log_id IS NOT NULL and comment.is_report = 0)) 
            
            )
            WHERE report_id = ?
        ", $id);
    }

    /**
     * @return \XF\Phrase
     */
    protected function getStatusType()
    {
        return \XF::phrase('report');
    }
}