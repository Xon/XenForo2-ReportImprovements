<?php

namespace SV\ReportImprovements\XF\Service\Thread;

use SV\ReportImprovements\Globals;
use XF\Entity\Post;

/**
 * Class ReplyBan
 * 
 * Extends \XF\Service\Thread\ReplyBan
 *
 * @package SV\ReportImprovements\XF\Service\Thread
 *
 * @property \SV\ReportImprovements\XF\Entity\ThreadReplyBan $replyBan
 */
class ReplyBan extends XFCP_ReplyBan
{
    /**
     * @param Post $post
     */
    public function setPost(Post $post)
    {
        $this->replyBan->post_id = $post->post_id;
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