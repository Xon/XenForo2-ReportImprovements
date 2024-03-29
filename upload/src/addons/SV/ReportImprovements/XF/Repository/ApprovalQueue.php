<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace SV\ReportImprovements\XF\Repository;

use XF\Entity\User;

/**
 * Class ApprovalQueue
 *
 * @see \XF\Repository\ApprovalQueue
 * @package SV\ReportImprovements\XF\Repository
 */
class ApprovalQueue extends XFCP_ApprovalQueue
{
    public function getUserDefaultFilters(User $user)
    {
        return $user->Option->sv_reportimprov_approval_filters ?? [];
    }

    public function saveUserDefaultFilters(User $user, array $filters)
    {
        $user->Option->fastUpdate('sv_reportimprov_approval_filters', $filters ?: null);
    }

    public function findUnapprovedContent()
    {
        return parent::findUnapprovedContent()
            ->with('Report');
    }
}