<?php

namespace SV\ReportImprovements\Job;

use XF\Job\AbstractRebuildJob;

/**
 * Class ResolveInactiveReport
 *
 * @package SV\ReportImprovements\Job
 */
class ResolveInactiveReport extends AbstractRebuildJob
{
    /**
     * @param int $start
     * @param int $batch
     *
     * @return array
     */
    protected function getNextIds($start, $batch)
    {
        $daysLimit = (int) $this->app->options()->sv_ri_expiry_days;
        if ($daysLimit === 0)
        {
            return null;
        }

        $db = $this->app->db();

        return $db->fetchAllColumn($db->limit(
            '
				SELECT report_id
				FROM xf_report
				WHERE report_id > ?
				  AND report_state = ?
				  AND last_modified_date <= ?
				ORDER BY report_id
			', $batch
        ), [$start, 'open', \XF::$time - (60 * 60 * 24 * $daysLimit)]);
    }

    /**
     * @param $id
     */
    protected function rebuildById($id)
    {
        /** @var \SV\ReportImprovements\XF\Entity\Report $report */
        if ($report = $this->app->em()->find('XF:Report', $id))
        {
            /** @var \SV\ReportImprovements\XF\Service\Report\Commenter $commenterService */
            $commenterService = $this->app->service('XF:Report\Commenter', $report);
            $commenterService->setReportState('resolved');
            if ($commenterService->validate($errors))
            {
                $commenterService->save();
            }
        }
    }

    /**
     * @return |null
     */
    protected function getStatusType()
    {
        return null;
    }

    /**
     * @return string
     */
    public function getStatusMessage()
    {
        $actionPhrase = \XF::phrase('svReportImprov_resolving_inactive_reports');
        return sprintf('%s... (%s)', $actionPhrase, $this->data['start']);
    }
}