<?php

namespace SV\ReportImprovements\XF\Service\Thread;

use SV\ReportImprovements\XF\Entity\ThreadReplyBan;
use XF\Entity\Post;

/**
 * Class ReplyBan
 * @extends \XF\Service\Thread\ReplyBan
 *
 * @package SV\ReportImprovements\XF\Service\Thread
 * @property ThreadReplyBan $replyBan
 */
class ReplyBan extends XFCP_ReplyBan
{
    /**
     * @return ThreadReplyBan
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