<?php

namespace SV\ReportImprovements\XF\Service\Thread;

use SV\ReportImprovements\XF\Entity\ThreadReplyBan as ExtendedThreadReplyBanEntity;
use XF\Entity\Post;

/**
 * @extends \XF\Service\Thread\ReplyBan
 * @property ?ExtendedThreadReplyBanEntity $replyBan
 */
class ReplyBan extends XFCP_ReplyBan
{
    /**
     * @return ?ExtendedThreadReplyBanEntity
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