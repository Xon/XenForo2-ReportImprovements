<?php

namespace SV\ReportImprovements\Job;

use SV\ReportImprovements\Enums\WarningType;
use SV\ReportImprovements\Globals;
use SV\ReportImprovements\Service\WarningLog\Creator;
use SV\ReportImprovements\XF\Entity\Warning as WarningEntity;
use SV\StandardLib\Helper;
use XF\Entity\Post;
use XF\Entity\User;
use XF\Entity\Warning;
use XF\Job\AbstractRebuildJob;

class WarningLogMigration extends AbstractRebuildJob
{
    /**
     * @param int $start
     * @param int $batch
     * @return array
     */
    protected function getNextIds($start, $batch)
    {
        $db = \XF::db();

        return $db->fetchAllColumn($db->limit(
            '
				SELECT warning_id
				FROM xf_warning
				WHERE warning_id > ?
				  AND NOT exists (SELECT warning_id FROM xf_sv_warning_log WHERE xf_sv_warning_log.warning_id = xf_warning.warning_id)
				ORDER BY warning_id
			', $batch
        ), [$start]);
    }

    /**
     * @param int $id
     * @throws \Exception
     */
    protected function rebuildById($id)
    {
        $warning = Helper::find(Warning::class, $id, ['User', 'WarnedBy']);
        if ($warning instanceof WarningEntity)
        {
            // On a detached thread, just pretend the post no longer exists
            $content = $warning->Content ?? null;
            if ($content instanceof Post && $content->Thread === null)
            {
                $warning->setContent(null);
            }
            Globals::$expiringFromCron = false;
            $user = $warning->WarnedBy;
            if (!$user)
            {
                $expireUserId = (int)(\XF::options()->svReportImpro_ExpireUserId ?? 1);
                $user = Helper::find(User::class, $expireUserId);
                if (!$user)
                {
                    $user = Helper::find(User::class, 1);
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
                    /** @var Creator $warningLogCreator */
                    $warningLogCreator = Helper::service(Creator::class, $warning, WarningType::New);
                    $warningLogCreator->setAutoResolve(true, false, '');
                    $warningLogCreator->setCanReopenReport(false);
                    $warningLogCreator->setAutoResolveNewReports(true);
                    if ($warningLogCreator->validate($errors))
                    {
                        $warningLogCreator->save();
                    }
                }
                catch (\Exception $e)
                {
                    // setupReportEntityContent can throw if the child content is detached from the parent
                    // but there is really no sane way to detect this ahead of time
                    if (\stripos($e->getMessage(), 'Attempt to read property') !== false)
                    {
                        \XF::logException($e);

                        return;
                    }
                    throw $e;
                }
                finally
                {
                    \XF::$time = $time;
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
        $actionPhrase = \XF::phrase('svReportImprov_migrating');

        return sprintf('%s... (%s)', $actionPhrase, $this->data['start']);
    }
}