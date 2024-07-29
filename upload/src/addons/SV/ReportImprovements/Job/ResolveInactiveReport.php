<?php

namespace SV\ReportImprovements\Job;

use SV\ReportImprovements\XF\Entity\Report;
use SV\ReportImprovements\XF\Service\Report\Commenter;
use SV\StandardLib\Helper;
use XF\Entity\User;
use XF\Job\AbstractRebuildJob;
use XF\Mvc\Entity\AbstractCollection;

/**
 * Class ResolveInactiveReport
 *
 * @package SV\ReportImprovements\Job
 */
class ResolveInactiveReport extends AbstractRebuildJob
{
    /** @var User|null */
    protected $reporter = null;

    /**  @var int */
    protected $daysLimit;
    /** @var string */
    protected $expireAction;
    /**  @var int */
    protected $expireUserId;

    protected function setupData(array $data)
    {
        $options = \XF::app()->options();
        $this->daysLimit = (int)($options->svReportImpro_autoExpireDays ?? 0);
        $this->expireAction = (string)($options->svReportImpro_autoExpireAction ?? '');
        $this->expireUserId = (int)($options->svReportImpro_expireUserId ?? 1);

        $this->reporter = \SV\StandardLib\Helper::find(\XF\Entity\User::class, $this->expireUserId);
        if (!$this->reporter)
        {
            $this->reporter = \SV\StandardLib\Helper::find(\XF\Entity\User::class, 1);
        }
        if (!$this->reporter)
        {
            \XF::logError('Require Comment Reporter (svReportImpro_expireUserId) to point to a valid user');
        }

        return parent::setupData($data);
    }

    /**
     * @param int $start
     * @param int $batch
     * @return array
     */
    protected function getNextIds($start, $batch)
    {
        if ($this->daysLimit <= 0 ||
            \strlen($this->expireAction) === 0 ||
            $this->reporter === null)
        {
            return null;
        }

        $db = \XF::db();

        return $db->fetchAllColumn($db->limit(
            '
				SELECT report_id
				FROM xf_report
				WHERE report_id > ?
				  AND report_state = ?
				  AND last_modified_date <= ?
				ORDER BY report_id
			', $batch
        ), [$start, 'open', \XF::$time - (60 * 60 * 24 * $this->daysLimit)]);
    }

    /**
     * @param int $id
     * @throws \Exception
     */
    protected function rebuildById($id)
    {
        /** @var Report $report */
        $report = \SV\StandardLib\Helper::find(\XF\Entity\Report::class, $id);
        if ($report === null)
        {
            return;
        }

        \XF::asVisitor($this->reporter, function () use ($report) {
            /** @var Commenter $commenterService */
            $commenterService = Helper::service(\XF\Service\Report\Commenter::class, $report);
            $commenterService->setReportState($this->expireAction);
            if ($commenterService->validate($errors))
            {
                $commenterService->save();
            }
            else
            {
                /** @var AbstractCollection|array|string $errors */
                if ($errors instanceof AbstractCollection)
                {
                    $errors = $errors->toArray();
                }
                else if (!\is_array($errors))
                {
                    $errors = [$errors];
                }

                foreach ($errors as &$string)
                {
                    $string = (string)$string;
                }
                /** @var array<string> $errors */
                \XF::logError('Error resolving inactive report:' . \var_export($errors, true));
            }
        });
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