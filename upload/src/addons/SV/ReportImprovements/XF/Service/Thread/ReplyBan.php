<?php

namespace SV\ReportImprovements\XF\Service\Thread;

use SV\ReportImprovements\Globals;
use XF\Entity\Post;
use XF\Entity\ThreadReplyBan;
use XF\Mvc\Entity\Entity;
use XF\PrintableException;

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
}