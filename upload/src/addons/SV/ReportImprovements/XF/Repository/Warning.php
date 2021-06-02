<?php

namespace SV\ReportImprovements\XF\Repository;

use SV\ReportImprovements\Globals;
use XF\Entity\User as UserEntity;
use XF\Entity\Warning as WarningEntity;
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

    public function processExpiredWarningsForUser(UserEntity $user, bool $checkBannedStatus): bool
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
     * @param WarningEntity $warning
     * @param string        $type
     * @param boolean       $resolveReport
     * @param bool          $alert
     * @param string        $alertComment
     * @throws \Exception
     */
    public function logOperation(WarningEntity $warning, string $type, bool $resolveReport, bool $alert, string $alertComment)
    {
        $reporter = \XF::visitor();
        $options = \XF::options();
        $expiringFromCron = Globals::$expiringFromCron;
        if ($expiringFromCron || !$reporter->user_id)
        {
            $expireUserId = (int)($options->svReportImpro_expireUserId ?? 1);
            $reporter = $this->app()->find('XF:User', $expireUserId);
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

        \XF::asVisitor($reporter, function () use ($reporter, $warning, $type, $resolveReport, $expiringFromCron, $alert, $alertComment) {
            /** @var \SV\ReportImprovements\Service\WarningLog\Creator $warningLogCreator */
            $warningLogCreator = $this->app()->service('SV\ReportImprovements:WarningLog\Creator', $warning, $type);
            $warningLogCreator->setAutoResolve($resolveReport, $alert, $alertComment);
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

        if (\in_array('' . $warningDefinitionId, $options->svSkipReplyBansForWarning ?? [], true))
        {
            return 'none';
        }

        return $options->sv_replyban_on_warning ?? '';
    }
}