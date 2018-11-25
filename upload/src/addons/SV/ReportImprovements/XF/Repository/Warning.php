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
            if (!$reporter)
            {
                $reporter = \XF::visitor();
            }
        }

        \XF::asVisitor($reporter, function () use ($warning, $type) {
            /** @var \SV\ReportImprovements\Service\WarningLog\Creator $warningLogCreator */
            $warningLogCreator = $this->app()->service('SV\ReportImprovements:WarningLog\Creator', $warning, $type);
            if ($warningLogCreator->validate($errors))
            {
                $warningLogCreator->save();
            }
        });
    }
}