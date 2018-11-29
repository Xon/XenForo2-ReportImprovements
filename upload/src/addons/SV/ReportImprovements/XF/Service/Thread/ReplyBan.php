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

    /**
     * @return array
     */
    protected function _validate()
    {
        try
        {
            return parent::_validate();
        }
        finally
        {
            Globals::$allowSavingReportComment = true;
        }
    }

    /**
     * @return \XF\Entity\ThreadReplyBan|\XF\Mvc\Entity\Entity|null
     */
    protected function _save()
    {
        try
        {
            return parent::_save();
        }
        finally
        {
            Globals::$allowSavingReportComment = true;
        }
    }
}