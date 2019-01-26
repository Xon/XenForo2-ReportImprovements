<?php

namespace SV\ReportImprovements\Job;

use SV\ReportImprovements\Globals;
use XF\Job\AbstractRebuildJob;

/**
 * Class WarningLogMigration
 *
 * @package SV\ReportImprovements\Job
 */
class WarningLogMigration extends AbstractRebuildJob
{
    /**
     * @param int $start
     * @param int $batch
     *
     * @return array
     */
    protected function getNextIds($start, $batch)
    {
        $db = $this->app->db();

        return $db->fetchAllColumn($db->limit(
            '
				SELECT warning_id
				FROM xf_warning
				WHERE warning_id > ?
				  AND not exists (SELECT warning_id FROM xf_sv_warning_log where xf_sv_warning_log.warning_id = xf_warning.warning_id)
				ORDER BY warning_id
			', $batch
        ), [$start]);
    }

    /**
     * @param $id
     * @throws \Exception
     */
    protected function rebuildById($id)
    {
        /** @var \SV\ReportImprovements\XF\Entity\Warning $warning */
        $warning = $this->app->em()->find('XF:Warning', $id);
        if ($warning)
        {
            Globals::$expiringFromCron = false;
            $user = $warning->WarnedBy;
            if (!$user)
            {
                $user = \XF::app()->find('XF:User', \XF::options()->sv_ri_user_id ?: 1);
                if (!$user)
                {
                    $user = \XF::app()->find('XF:User', 1);
                }
                if (!$user && $warning->User)
                {
                    $user = $warning->User;
                }
            }

            \XF::asVisitor($user, function () use ($warning) {
                $time = \XF::$time;
                \XF::$time = $warning->warning_date;
                try
                {
                    /** @var \SV\ReportImprovements\Service\WarningLog\Creator $warningLogCreator */
                    $warningLogCreator = \XF::app()->service('SV\ReportImprovements:WarningLog\Creator', $warning, 'new');
                    $warningLogCreator->setAutoResolve(false);
                    if ($warningLogCreator->validate($errors))
                    {
                        $db = \XF::db();
                        $db->beginTransaction();

                        $warningLogCreator->save();
                        $report = $warningLogCreator->getReport();
                        if ($report && $report->exists())
                        {
                            if ($warning->warning_date < $report->first_report_date)
                            {
                                $report->first_report_date = $warning->warning_date;
                            }
                            $last_modified_date = $report->last_modified_date;
                            if ($warning->warning_date > $last_modified_date || $last_modified_date === \XF::$time)
                            {
                                $report->last_modified_date = $warning->warning_date;
                            }
                            if ($report->isChanged('last_modified_date'))
                            {
                                $report->last_modified_id = 0;
                            }
                            $report->saveIfChanged($saved, true, false);
                        }

                        $db->commit();
                    }
                }
                finally
                {
                    \XF::$time = $time;
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
        $actionPhrase = \XF::phrase('svReportImprov_migrating');
        return sprintf('%s... (%s)', $actionPhrase, $this->data['start']);
    }
}