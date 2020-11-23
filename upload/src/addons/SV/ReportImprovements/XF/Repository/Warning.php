<?php

namespace SV\ReportImprovements\XF\Repository;

use SV\ReportImprovements\Globals;
use XF\Entity\User as UserEntity;
use XF\Entity\WarningDefinition;

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
     * @noinspection PhpMissingParamTypeInspection
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
     * @param string             $type
     * @param boolean            $resolveReport
     * @throws \Exception
     */
    public function logOperation(\XF\Entity\Warning $warning, string $type, bool $resolveReport)
    {
        $reporter = \XF::visitor();
        $options = \XF::options();
        $expiringFromCron = Globals::$expiringFromCron;
        if ($expiringFromCron || !$reporter->user_id)
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

        \XF::asVisitor($reporter, function () use ($reporter, $warning, $type, $resolveReport, $expiringFromCron) {
            /** @var \SV\ReportImprovements\Service\WarningLog\Creator $warningLogCreator */
            $warningLogCreator = $this->app()->service('SV\ReportImprovements:WarningLog\Creator', $warning, $type);
            $warningLogCreator->setAutoResolve($resolveReport);
            if ($expiringFromCron)
            {
                $warningLogCreator->setCanReopenReport(false);
            }
            if ($warningLogCreator->validate($errors))
            {
                $warningLogCreator->save();
                \XF::runLater(function () use ($warningLogCreator, $reporter) {
                    \XF::asVisitor($reporter, function () use ($warningLogCreator) {
                        $warningLogCreator->sendNotifications();
                    });
                });
            }
        });
    }

    /**
     * @param WarningDefinition|int $warningDefinition
     * @return string
     */
    public function getReplyBanForWarningDefinition($warningDefinition)
    {
        $warningDefinitionId = 0;
        if ($warningDefinition instanceof WarningDefinition)
        {
            $warningDefinitionId = $warningDefinition->warning_definition_id;
        }
        else if (\is_numeric($warningDefinitionId))
        {
            $warningDefinitionId = (int)$warningDefinitionId;
        }

        $options = \XF::options();

        if (in_array('' . $warningDefinitionId, $options->svSkipReplyBansForWarning, true))
        {
            return 'none';
        }

        return $options->sv_replyban_on_warning;
    }
}