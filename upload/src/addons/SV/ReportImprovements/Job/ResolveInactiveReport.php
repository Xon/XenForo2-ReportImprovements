<?php

namespace SV\ReportImprovements\Job;

use SV\ReportImprovements\XF\Entity\Report as ExtendedReportEntity;
use SV\ReportImprovements\XF\Service\Report\Commenter as ExtendedReportCommenterService;
use SV\StandardLib\Helper;
use XF\Entity\Report as ReportEntity;
use XF\Entity\User as UserEntity;
use XF\Job\AbstractRebuildJob;
use XF\Mvc\Entity\AbstractCollection;
use XF\Service\Report\Commenter as ReportCommenterService;

class ResolveInactiveReport extends AbstractRebuildJob
{
    /** @var UserEntity|null */
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

        $this->reporter = Helper::find(UserEntity::class, $this->expireUserId);
        if (!$this->reporter)
        {
            $this->reporter = Helper::find(UserEntity::class, 1);
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
        /** @var ExtendedReportEntity $report */
        $report = Helper::find(ReportEntity::class, $id);
        if ($report === null)
        {
            return;
        }

        \XF::asVisitor($this->reporter, function () use ($report) {
            /** @var ExtendedReportCommenterService $commenterService */
            $commenterService = Helper::service(ReportCommenterService::class, $report);
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