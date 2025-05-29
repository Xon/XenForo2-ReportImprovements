<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace SV\ReportImprovements\XF\Repository;

use XF\Entity\User as UserEntity;

/**
 * @extends \XF\Repository\ApprovalQueue
 */
class ApprovalQueue extends XFCP_ApprovalQueue
{
    public function getUserDefaultFilters(UserEntity $user)
    {
        return $user->Option->sv_reportimprov_approval_filters ?? [];
    }

    public function saveUserDefaultFilters(UserEntity $user, array $filters)
    {
        $user->Option->fastUpdate('sv_reportimprov_approval_filters', $filters ?: null);
    }
}