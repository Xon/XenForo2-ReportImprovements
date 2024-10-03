<?php

namespace SV\ReportImprovements\XF\Service\Thread;

use SV\ReportImprovements\XF\Entity\ThreadReplyBan as ExtendedThreadReplyBanEntity;
use XF\Entity\Post as PostEntity;

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
     * @param PostEntity $post
     */
    public function setPost(PostEntity $post)
    {
        $this->replyBan->post_id = $post->post_id;
    }
}