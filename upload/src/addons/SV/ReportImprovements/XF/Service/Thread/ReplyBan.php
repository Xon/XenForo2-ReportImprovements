<?php

namespace SV\ReportImprovements\XF\Service\Thread;

use SV\ReportImprovements\Globals;
use XF\Entity\Post;

/**
 * Class ReplyBan
 * Extends \XF\Service\Thread\ReplyBan
 *
 * @package SV\ReportImprovements\XF\Service\Thread
 * @property \SV\ReportImprovements\XF\Entity\ThreadReplyBan $replyBan
 */
class ReplyBan extends XFCP_ReplyBan
{
    /**
     * @return \SV\ReportImprovements\XF\Entity\ThreadReplyBan
     */
    public function getReplyBan()
    {
        return $this->replyBan;
    }

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
        $oldVal = Globals::$allowSavingReportComment;
        Globals::$allowSavingReportComment = true;
        try
        {
            return parent::_validate();
        }
        finally
        {
            Globals::$allowSavingReportComment = $oldVal;
        }
    }

    /**
     * @return \XF\Entity\ThreadReplyBan|\XF\Mvc\Entity\Entity|null
     */
    protected function _save()
    {
        $oldVal = Globals::$allowSavingReportComment;
        Globals::$allowSavingReportComment = true;
        try
        {
            return parent::_save();
        }
        finally
        {
            Globals::$allowSavingReportComment = $oldVal;
        }
    }
}