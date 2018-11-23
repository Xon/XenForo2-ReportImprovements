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
 *
 * RELATIONS
 * @property \SV\ReportImprovements\XF\Entity\Report Report
 */
class ThreadReplyBan extends XFCP_ThreadReplyBan
{
    protected function _postSave()
    {
        parent::_postSave();

        $type = null;
        if ($this->isInsert())
        {
            $type = 'new';
        }
        else if ($this->isUpdate() && $this->hasChanges())
        {
            $type = 'edit';
            if (\XF::$time >= $this->expiry_date)
            {
                $type = 'expire';
            }
        }

        if ($type)
        {
            /** @var \SV\ReportImprovements\XF\Repository\ThreadReplyBan $threadReplyBanRepo */
            $threadReplyBanRepo = $this->repository('XF:ThreadReplyBan');
            $threadReplyBanRepo->logToReport($this, $type);
        }
    }

    protected function _postDelete()
    {
        parent::_postDelete();

        /** @var \SV\ReportImprovements\XF\Repository\ThreadReplyBan $threadReplyBanRepo */
        $threadReplyBanRepo = $this->repository('XF:ThreadReplyBan');
        $threadReplyBanRepo->logToReport($this, 'delete');
    }

    /**
     * @param Structure $structure
     *
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->relations['Report'] = [
            'entity' => 'XF:Report',
            'type' => self::TO_ONE,
            'conditions' => [
                ['content_type', '=', 'user'],
                ['content_id', '=', '$user_id']
            ]
        ];
    
        return $structure;
    }
}