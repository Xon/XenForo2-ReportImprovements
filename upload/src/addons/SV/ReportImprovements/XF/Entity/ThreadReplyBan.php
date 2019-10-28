<?php

namespace SV\ReportImprovements\XF\Entity;

use XF\Mvc\Entity\Structure;

/**
 * Class ThreadReplyBan
 * Extends \XF\Entity\ThreadReplyBan
 *
 * @package SV\ReportImprovements\XF\Entity
 * COLUMNS
 * @property int    post_id
 * RELATIONS
 * @property Report Report
 * @property Post   Post
 */
class ThreadReplyBan extends XFCP_ThreadReplyBan
{
    protected function _postSave()
    {
        parent::_postSave();

        if ($this->getOption('svLogWarningChanges'))
        {
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
                $threadReplyBanRepo->logToReport($this, $type, $this->getOption('svResolveReport'));
            }
        }
    }

    protected function _postDelete()
    {
        parent::_postDelete();

        if ($this->getOption('svLogWarningChanges'))
        {
            $type = 'delete';
            if (\XF::$time >= $this->expiry_date)
            {
                $type = 'expire';
            }

            /** @var \SV\ReportImprovements\XF\Repository\ThreadReplyBan $threadReplyBanRepo */
            $threadReplyBanRepo = $this->repository('XF:ThreadReplyBan');
            $threadReplyBanRepo->logToReport($this, $type, $this->getOption('svResolveReport'));
        }
    }

    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns['post_id'] = ['type' => self::UINT, 'default' => null, 'nullable' => true];

        $structure->relations['Report'] = [
            'entity'     => 'XF:Report',
            'type'       => self::TO_ONE,
            'conditions' => [
                ['content_type', '=', 'user'],
                ['content_id', '=', '$user_id'],
            ],
        ];
        $structure->relations['Post'] = [
            'entity'     => 'XF:Post',
            'type'       => self::TO_ONE,
            'conditions' => 'post_id',
            'primary'    => true,
        ];

        $structure->options['svLogWarningChanges'] = true;
        $structure->options['svResolveReport'] = false;

        return $structure;
    }
}