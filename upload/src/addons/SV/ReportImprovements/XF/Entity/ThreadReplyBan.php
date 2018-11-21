<?php

namespace SV\ReportImprovements\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * Class ThreadReplyBan
 * 
 * Extends \XF\Entity\ThreadReplyBan
 *
 * @package SV\ReportImprovements\XF\Entity
 */
class ThreadReplyBan extends XFCP_ThreadReplyBan
{
    protected function _postSave()
    {
        parent::_postSave();
    }

    protected function _postDelete()
    {
        parent::_postDelete();
    }
}