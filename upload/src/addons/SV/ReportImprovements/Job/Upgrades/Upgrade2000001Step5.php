<?php

namespace SV\ReportImprovements\Job\Upgrades;

use XF\Entity\Report;
use XF\Job\AbstractRebuildJob;

/**
 * Class Upgrade2000001Step5
 *
 * @package SV\ReportImprovements\Job\Upgrades
 */
class Upgrade2000001Step5 extends AbstractRebuildJob
{
    /**
     * @param $start
     * @param $batch
     *
     * @return array
     */
    protected function getNextIds($start, $batch)
    {
        $db = $this->app->db();

        return $db->fetchAllColumn($db->limit(
            '
            SELECT report_id
            FROM xf_report
            WHERE report_id > ?'
            , $batch
        ), $start);
    }

    /**
     * @param $id
     *
     * @throws \XF\PrintableException
     */
    protected function rebuildById($id)
    {
        /** @var \XF\Entity\Report $report */
        $report = $this->app->em()->find('XF:Report', $id);

        $content = $report->Content;
        $handler = $report->getHandler();

        if ($content && $handler)
        {
            $handler->setupReportEntityContent($report, $content);
            $report->save();
        }
    }
    /**
     * @return \XF\Phrase
     */
    protected function getStatusType()
    {
        return \XF::phrase('svReportImprov_report_content_info_cache');
    }
}