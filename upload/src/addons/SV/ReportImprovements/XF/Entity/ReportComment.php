<?php

namespace SV\ReportImprovements\XF\Entity;

use SV\ReportImprovements\Globals;
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
 * @property int warning_log_id
 * @property bool alertSent
 * @property string alertComment
 *
 * GETTERS
 * @property \SV\ReportImprovements\XF\Entity\User ViewableUsername
 * @property \SV\ReportImprovements\XF\Entity\User ViewableUser
 *
 * RELATIONS
 * @property \XF\Entity\LikedContent[] Likes
 * @property \SV\ReportImprovements\Entity\WarningLog WarningLog
 * @property \SV\ReportImprovements\XF\Entity\Report Report
 */
class ReportComment extends XFCP_ReportComment
{
    public function canView()
    {
        if (!$this->Report)
        {
            return false;
        }

        return $this->Report->canView();
    }

    /**
     * @return bool
     */
    public function isLiked()
    {
        $visitor = \XF::visitor();
        if (!$visitor->user_id)
        {
            return false;
        }

        return isset($this->Likes[$visitor->user_id]);
    }

    /**
     * @return mixed|string|string[]|null
     */
    public function getViewableUsername()
    {
        return \XF::phrase('svReportImprov_content_reporter')->render();
    }

    /**
     * @return \XF\Entity\User|\SV\ReportImprovements\XF\Entity\User
     */
    public function getViewableUser()
    {
        if (!$this->is_report)
        {
            return $this->User;
        }

        if ($this->Report->canViewReporter($error))
        {
            return $this->User;
        }

        /** @var \XF\Repository\User $userRepo */
        $userRepo = $this->repository('XF:User');
        return $userRepo->getGuestUser($this->ViewableUsername);
    }

    /**
     * @param Structure $structure
     *
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->contentType = 'report_comment';

        $structure->columns['warning_log_id'] = ['type' => self::UINT, 'default' => null, 'nullable' => true];
        $structure->columns['alertSent'] = ['type' => self::BOOL, 'default' => false];
        $structure->columns['alertComment'] = ['type' => self::STR, 'default' => null, 'nullable' => true];
        $structure->columns['likes'] = ['type' => self::UINT, 'forced' => true, 'default' => 0];
        $structure->columns['like_users'] = ['type' => self::SERIALIZED_ARRAY, 'default' => []];

        $structure->behaviors['XF:Likeable'] = ['stateField' => ''];
        $structure->behaviors['XF:Indexable'] = [
            'checkForUpdates' => ['message', 'user_id', 'report_id', 'comment_date', 'state_change', 'is_report']
        ];
        $structure->getters['ViewableUsername'] = true;
        $structure->getters['ViewableUser'] = true;
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
        $structure->relations['WarningLog'] = [
            'entity' => 'SV\ReportImprovements:WarningLog',
            'type' => self::TO_ONE,
            'conditions' => 'warning_log_id',
            'primary' => true
        ];

        $structure->defaultWith[] = 'WarningLog';
        $structure->defaultWith[] = 'WarningLog.Warning';

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

        if ($this->user_id === $visitor->user_id)
        {
            $error = \XF::phraseDeferred('liking_own_content_cheating');
            return false;
        }

        return $visitor->hasPermission('general', 'reportLike');
    }

    /**
     * @return bool
     */
    public function hasSaveableChanges()
    {
        return parent::hasSaveableChanges() || $this->warning_log_id || Globals::$allowSavingReportComment;
    }
}