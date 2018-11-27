<?php

namespace SV\ReportImprovements\XF\Service\Thread;

use SV\ReportImprovements\Globals;

/**
 * Class ReplyBan
 * 
 * Extends \XF\Service\Thread\ReplyBan
 *
 * @package SV\ReportImprovements\XF\Service\Thread
 */
class ReplyBan extends XFCP_ReplyBan
{
    /**
     * @param int $postId
     * @param string $threadTitle
     */
    public function setPostIdForWarning($postId, $threadTitle)
    {
        Globals::$postIdForWarningLog = $postId;
        Globals::$threadTitleForWarningLog = $threadTitle;
    }
}