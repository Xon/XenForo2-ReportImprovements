<?php

namespace SV\ReportImprovements\XF\Repository;

use SV\ReportImprovements\Globals;
use XF\Entity\User as UserEntity;

/**
 * Class Warning
 * Extends \XF\Repository\Warning
 *
 * @package SV\ReportImprovements\XF\Repository
 */
class Warning extends XFCP_Warning
{
    public function processExpiredWarnings()
    {
        Globals::$expiringFromCron = true;
        try
        {
            parent::processExpiredWarnings();
        }
        finally
        {
            Globals::$expiringFromCron = null;
        }
    }

    /**
     * @param UserEntity $user
     * @param bool       $checkBannedStatus
     * @return bool
     */
    public function processExpiredWarningsForUser(UserEntity $user, $checkBannedStatus)
    {
        Globals::$expiringFromCron = true;
        try
        {
            /** @noinspection PhpUndefinedMethodInspection */
            return parent::processExpiredWarningsForUser($user, $checkBannedStatus);
        }
        finally
        {
            Globals::$expiringFromCron = null;
        }
    }

    /**
     * @param \XF\Entity\Warning $warning
     * @param                    $type
     * @throws \Exception
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
            $warningLogCreator->setAutoResolve(Globals::$resolveWarningReport);
            if ($warningLogCreator->validate($errors))
            {
                $warningLogCreator->save();
            }
            \XF::runLater(function () use ($warningLogCreator, $reporter) {
                \XF::asVisitor($reporter, function () use ($warningLogCreator) {
                    $warningLogCreator->sendNotifications();
                });
            });
        });
    }
}