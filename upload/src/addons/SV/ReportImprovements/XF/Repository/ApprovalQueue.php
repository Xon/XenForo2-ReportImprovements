<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace SV\ReportImprovements\XF\Repository;

/**
 * Class ApprovalQueue
 * @see \XF\Repository\ApprovalQueue
 *
 * @package SV\ReportImprovements\XF\Repository
 */
class ApprovalQueue extends XFCP_ApprovalQueue
{
    public function getUserDefaultFilters(\XF\Entity\User $user)
    {
        return $user->Option->sv_reportimprov_approval_filters ?? [];
    }

    public function saveUserDefaultFilters(\XF\Entity\User $user, array $filters)
    {
        $user->Option->fastUpdate('sv_reportimprov_approval_filters', $filters ?: null);
    }

    public function findUnapprovedContent()
    {
        return parent::findUnapprovedContent()
            ->with('Report');
    }
}