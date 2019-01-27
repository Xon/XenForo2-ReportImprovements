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
        $warning = $this->app->em()->find('XF:Warning', $id, ['User', 'WarnedBy']);
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
                Globals::$suppressReportStateChange = true;
                try
                {
                    /** @var \SV\ReportImprovements\Service\WarningLog\Creator $warningLogCreator */
                    $warningLogCreator = \XF::app()->service('SV\ReportImprovements:WarningLog\Creator', $warning, 'new');
                    $warningLogCreator->setAutoResolve(false);
                    if ($warningLogCreator->validate($errors))
                    {
                        $warningLogCreator->save();
                    }
                }
                finally
                {
                    \XF::$time = $time;
                    Globals::$suppressReportStateChange = false;
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