<?php

namespace SV\ReportImprovements\XF\Repository;

use SV\ReportImprovements\XF\Entity\ThreadReplyBan as ExtendedReplyBanEntity;
use XF\Entity\ThreadReplyBan as ReplyBanEntity;

/**
 * Class ThreadReplyBan
 * Extends \XF\Repository\ThreadReplyBan
 *
 * @package SV\ReportImprovements\XF\Repository
 */
class ThreadReplyBan extends XFCP_ThreadReplyBan
{
    /**
     * @param ReplyBanEntity|ExtendedReplyBanEntity $threadReplyBan
     * @param string                                $type
     * @param boolean                               $resolveReport
     * @param bool                                  $alert
     * @param string                                $alertComment
     */
    public function logToReport(ReplyBanEntity $threadReplyBan, string $type, bool $resolveReport, bool $alert, string $alertComment)
    {
        /** @var \SV\ReportImprovements\Service\WarningLog\Creator $warningLogCreator */
        $warningLogCreator = $this->app()->service('SV\ReportImprovements:WarningLog\Creator', $threadReplyBan, $type);
        $report = $threadReplyBan->Report;
        if ($report && !$report->isClosed())
        {
            $warningLogCreator->setAutoResolve($resolveReport, $alert, $alertComment);
        }
        if ($warningLogCreator->validate($errors))
        {
            $warningLogCreator->save();
        }
    }
}