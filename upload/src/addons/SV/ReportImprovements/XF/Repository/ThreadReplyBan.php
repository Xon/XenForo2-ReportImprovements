<?php

namespace SV\ReportImprovements\XF\Repository;

use SV\ReportImprovements\Globals;

/**
 * Class ThreadReplyBan
 * 
 * Extends \XF\Repository\ThreadReplyBan
 *
 * @package SV\ReportImprovements\XF\Repository
 */
class ThreadReplyBan extends XFCP_ThreadReplyBan
{
    /**
     * @param \XF\Entity\ThreadReplyBan|\SV\ReportImprovements\XF\Entity\ThreadReplyBan $threadReplyBan
     * @param                           $type
     */
    public function logToReport(\XF\Entity\ThreadReplyBan $threadReplyBan, $type)
    {
        /** @var \SV\ReportImprovements\Service\WarningLog\Creator $warningLogCreator */
        $warningLogCreator = $this->app()->service('SV\ReportImprovements:WarningLog\Creator', $threadReplyBan, $type);
        $post = $threadReplyBan->Post;
        if ($post && $post->Report && $post->Report->report_state !== 'resolved')
        {
            $warningLogCreator->setAutoResolve(Globals::$resolveThreadReplyBanReport);
        }
        if ($warningLogCreator->validate($errors))
        {
            $warningLogCreator->save();
        }
    }
}