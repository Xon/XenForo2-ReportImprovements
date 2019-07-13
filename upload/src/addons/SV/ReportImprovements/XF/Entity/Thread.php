<?php

namespace SV\ReportImprovements\XF\Entity;

class Thread extends XFCP_Thread
{
    public function canReplyBan(&$error = null)
    {
        $canReplyBan = parent::canReplyBan($error);

        if (!$canReplyBan && !$this->discussion_open)
        {
            $visitor = \XF::visitor();
            $canReplyBan = $visitor->user_id && $visitor->hasNodePermission($this->node_id, 'threadReplyBan');
        }

        return $canReplyBan;
    }
}