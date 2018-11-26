<?php

namespace SV\ReportImprovements\XF\Repository;

use SV\ReportImprovements\Globals;

/**
 * Class Warning
 * Extends \XF\Repository\Warning
 *
 * @package SV\ReportImprovements\XF\Repository
 */
class Warning extends XFCP_Warning
{
    /**
     * @noinspection PhpDocMissingThrowsInspection
     * @param \XF\Entity\Warning $warning
     * @param string             $type
     */
    public function logOperation(\XF\Entity\Warning $warning, $type)
    {
        $reporter = \XF::visitor();
        $options = \XF::options();
        if (Globals::$expiringFromCron || !$reporter->user_id)
        {
            $reporter = $this->app()->find('XF:User', $options->sv_ri_user_id ?: 1);
            if (!$reporter)
            {
                $reporter = $this->app()->find('XF:User', 1);
            }
            if (!$reporter && $warning->User)
            {
                $reporter = $warning->User;
            }
            if (!$reporter && $warning->WarnedBy)
            {
                $reporter = $warning->WarnedBy;
            }
            if (!$reporter)
            {
                $reporter = \XF::visitor();
            }
        }

        \XF::asVisitor($reporter, function () use ($reporter, $warning, $type) {
            /** @var \SV\ReportImprovements\Service\WarningLog\Creator $warningLogCreator */
            $warningLogCreator = $this->app()->service('SV\ReportImprovements:WarningLog\Creator', $warning, $type);
            if ($warningLogCreator->validate($errors))
            {
                $warningLogCreator->save();
            }
            \XF::runLater(function() use($warningLogCreator, $reporter) {
                \XF::asVisitor($reporter, function () use ($warningLogCreator) {
                    $warningLogCreator->sendNotifications();
                });
            });
        });
    }

    public function resolveReport(\XF\Entity\Warning $warning)
    {
        /** @var \XF\Service\Report\Commenter $commenter */
        $commenter = $this->app()->service('XF:Report\Commenter', $this->Report);
        $commenter->setReportState('resolved');
        if (!$commenter->validate($errors))
        {
            throw $this->exception($this->error($errors));
        }
        $commenter->save();
        $commenter->sendNotifications();

        $report = $commenter->getReport();
        $report->draft_comment->delete();

        $this->app()->session()->reportLastRead = \XF::$time;
    }
}