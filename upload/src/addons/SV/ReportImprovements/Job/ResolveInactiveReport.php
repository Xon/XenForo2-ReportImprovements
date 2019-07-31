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
    /** @var \XF\Entity\User */
    protected $reporter;

    /**
     * @param int $start
     * @param int $batch
     * @return array
     */
    protected function getNextIds($start, $batch)
    {
        $options = $this->app->options();
        $daysLimit = (int)$options->sv_ri_expiry_days;
        if ($daysLimit <= 0 || !$options->sv_ri_expiry_action)
        {
            return null;
        }

        $app = $this->app;
        $options = $app->options();
        /** @var  $reporter */
        $this->reporter = $app->find('XF:User', $options->sv_ri_user_id ?: 1);
        if (!$this->reporter)
        {
            $this->reporter = $app->find('XF:User', 1);
        }
        if (!$this->reporter)
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
     * @throws \Exception
     */
    protected function rebuildById($id)
    {
        /** @var \SV\ReportImprovements\XF\Entity\Report $report */
        $report = $this->app->em()->find('XF:Report', $id);
        if ($report)
        {
            \XF::asVisitor($this->reporter, function () use ($report) {
                /** @var \SV\ReportImprovements\XF\Service\Report\Commenter $commenterService */
                $commenterService = $this->app->service('XF:Report\Commenter', $report);
                $commenterService->setReportState($this->app->options()->sv_ri_expiry_action);
                if ($commenterService->validate($errors))
                {
                    $commenterService->save();
                }
            });
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