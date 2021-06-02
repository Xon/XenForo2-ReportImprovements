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
        $daysLimit = (int)($options->svReportImpro_autoExpireDays ?? 0);
        $expireAction = $options->svReportImpro_autoExpireAction ?? '';
        if ($daysLimit <= 0 || !$expireAction)
        {
            return null;
        }

        $app = $this->app;
        $options = $app->options();
        /** @var  $reporter */
        $expireUserId = (int)($options->svReportImpro_expireUserId ?? 1);
        $this->reporter = \XF::app()->find('XF:User', $expireUserId);
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
     * @param int $id
     * @throws \Exception
     */
    protected function rebuildById($id)
    {
        $expireAction = $options->svReportImpro_autoExpireAction ?? '';
        /** @var \SV\ReportImprovements\XF\Entity\Report $report */
        $report = $this->app->em()->find('XF:Report', $id);
        if ($report && $expireAction)
        {
            \XF::asVisitor($this->reporter, function () use ($report, $expireAction) {
                /** @var \SV\ReportImprovements\XF\Service\Report\Commenter $commenterService */
                $commenterService = $this->app->service('XF:Report\Commenter', $report);
                $commenterService->setReportState($expireAction);
                if ($commenterService->validate($errors))
                {
                    $commenterService->save();
                }
            });
        }
    }

    /**
     * @return null
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