<?php

namespace SV\ReportImprovements;

/**
 * Needs to remain as a class to enable upgrades to work smoothly
 *
 * @deprecated
 */
class Listener
{
    /**
     * @noinspection PhpUnusedParameterInspection
     */
    public static function criteriaUser(string $rule, array $data, \XF\Entity\User $user, bool &$eventReturnValue): bool
    {
        return true;
    }

    public static function appPubStartEnd(\XF\Pub\App $app)
    {

    }
}