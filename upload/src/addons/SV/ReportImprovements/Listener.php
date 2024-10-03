<?php

namespace SV\ReportImprovements;

use XF\Entity\User as UserEntity;
use XF\Pub\App;

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
    public static function criteriaUser(string $rule, array $data, UserEntity $user, bool &$eventReturnValue): bool
    {
        return true;
    }

    public static function appPubStartEnd(App $app)
    {

    }
}