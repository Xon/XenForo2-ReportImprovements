<?php

namespace SV\ReportImprovements\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * Class Warning
 * 
 * Extends \XF\Entity\Warning
 *
 * @package SV\ReportImprovements\XF\Entity
 *
 * RELATIONS
 * @property \SV\ReportImprovements\XF\Entity\Report Report
 */
class Warning extends XFCP_Warning
{
    protected function _postSave()
    {
        parent::_postSave();

        if ($this->content_type === 'post')
        {
            /** @var \XF\Entity\Post $content */
            $content = $this->Content;

            if ($replyOnWarning = $this->app()->options()->sv_replyban_on_warning)
            {
                $reason = $this->title;
                if (!empty($replyOnWarning['reason_reply_ban']))
                {
                    $reason = $replyOnWarning['reason_reply_ban'];
                }

                if ($replyOnWarning['ban_length'] === 'permanent')
                {
                    $expiryDate = null;
                }
                else
                {
                    $expiryDate = min(
                        pow(2, 32) - 1,
                        strtotime("+{$replyOnWarning['ban_length_value']} {$replyOnWarning['ban_length_unit']}")
                    );
                }

                /** @var \XF\Service\Thread\ReplyBan $replyBanSvc */
                $replyBanSvc = $this->app()->service('XF\Service:Thread\ReplyBan', $content->Thread, $this->User);
                $replyBanSvc->setReason($reason);
                $replyBanSvc->setExpiryDate($expiryDate);
                $replyBanSvc->setSendAlert($replyOnWarning['send_alert']);
                if ($replyBanSvc->validate($errors))
                {
                    $replyBanSvc->save();
                }
            }
        }
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
                ['content_type', '=', '$content_type'],
                ['content_id', '=', '$content_id']
            ]
        ];
    
        return $structure;
    }
}