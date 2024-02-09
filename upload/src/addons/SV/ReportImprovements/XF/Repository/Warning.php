<?php

namespace SV\ReportImprovements\XF\Repository;

use SV\ReportImprovements\Globals;
use XF\Entity\User as UserEntity;

/**
 * Class Warning
 * @extends \XF\Repository\Warning
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

    public function getReplyBanForWarningDefinition(int $warningDefinitionId): string
    {
        $options = \XF::options();

        if (\in_array('' . $warningDefinitionId, $options->svSkipReplyBansForWarning ?? [], true))
        {
            return 'none';
        }

        return $options->sv_replyban_on_warning ?? '';
    }
}