<?php

namespace SV\ReportImprovements\XF\Entity;

use XF\Mvc\Entity\Structure;

/**
 * Class ReportComment
 *
 * Extends \XF\Entity\ReportComment
 *
 * @package SV\ReportImprovements\XF\Entity
 *
 * COLUMNS
 * @property int likes
 * @property array like_users
 * @property bool alertSent
 * @property string alertComment
 *
 * RELATIONS
 * @property \XF\Entity\LikedContent[] Likes
 */
class ReportComment extends XFCP_ReportComment
{
    /**
     * @param Structure $structure
     *
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->contentType = 'report_comment';

        $structure->columns['alertSent'] = ['type' => self::BOOL, 'default' => null, 'nullable' => null];
        $structure->columns['alertComment'] = ['type' => self::STR, 'default' => null, 'nullable' => null];

        $structure->behaviors['XF:Likeable'] = ['stateField' => ''];
        $structure->behaviors['XF:Indexable'] = [
            'checkForUpdates' => ['message', 'user_id', 'report_id', 'comment_date', 'state_change', 'is_report']
        ];

        $structure->relations['Likes'] = [
            'entity' => 'XF:LikedContent',
            'type' => self::TO_MANY,
            'conditions' => [
                ['content_type', '=', 'report_comment'],
                ['content_id', '=', '$report_comment_id']
            ],
            'key' => 'like_user_id',
            'order' => 'like_date'
        ];

        return $structure;
    }

    protected function _postSave()
    {
        parent::_postSave();

        if ($this->isInsert())
        {
            $this->Report->fastUpdate('last_modified_id', $this->report_comment_id);
        }
    }

    protected function _postDelete()
    {
        parent::_postDelete();

        if ($this->Report)
        {
            $lastReportCommentFinder = $this->finder('XF:ReportComment');
            $lastReportCommentFinder->where('report_id', $this->report_id);
            $lastReportCommentFinder->order('comment_date', 'DESC');

            /** @var \SV\ReportImprovements\XF\Entity\ReportComment $lastReportComment */
            $lastReportComment = $lastReportCommentFinder->fetchOne();
            if ($lastReportComment)
            {
                $this->Report->fastUpdate('last_modified_id', $lastReportComment->report_comment_id);
            }
        }
    }

    /**
     * @param null $error
     *
     * @return bool
     */
    public function canLike(&$error = null)
    {
        $visitor = \XF::visitor();
        if (!$visitor->user_id)
        {
            return false;
        }

        if ($this->Report->isClosed())
        {
            return false;
        }

        if ($this->user_id === $visitor->user_id)
        {
            $error = \XF::phraseDeferred('liking_own_content_cheating');
            return false;
        }

        return $visitor->hasPermission('general', 'reportLike');
    }
}