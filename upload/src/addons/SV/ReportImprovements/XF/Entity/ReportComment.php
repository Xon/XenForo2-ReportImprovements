<?php

namespace SV\ReportImprovements\XF\Entity;

use SV\ReportImprovements\Globals;
use XF\Entity\ReactionTrait;
use XF\Mvc\Entity\Structure;

/**
 * Class ReportComment
 * Extends \XF\Entity\ReportComment
 *
 * @package SV\ReportImprovements\XF\Entity
 * COLUMNS
 * @property int reaction_score
 * @property array reactions_
 * @property array reaction_users_
 * @property int                                      warning_log_id
 * @property bool                                     alertSent
 * @property string                                   alertComment
 * @property int|null                                 assigned_user_id
 * @property string                                   assigned_username
 *
 * GETTERS
 * @property string                                   ViewableUsername
 * @property User                                     ViewableUser
 * @property mixed reactions
 * @property mixed reaction_users
 *
 * RELATIONS
 * @property \XF\Entity\LikedContent[]                Likes
 * @property \SV\ReportImprovements\Entity\WarningLog WarningLog
 * @property Report                                   Report
 */
class ReportComment extends XFCP_ReportComment
{
    use ReactionTrait;

    public function canView()
    {
        if (!$this->Report)
        {
            return false;
        }

        return $this->Report->canView();
    }

    /**
     * @param null $error
     *
     * @return bool
     */
    public function canReact(&$error = null)
    {
        $visitor = \XF::visitor();
        if (!$visitor->user_id)
        {
            return false;
        }

        if ($this->user_id === $visitor->user_id)
        {
            $error = \XF::phraseDeferred('reacting_to_your_own_content_is_considered_cheating');

            return false;
        }

        return $visitor->hasPermission('general', 'reportReact');
    }

    /**
     * @return bool
     */
    public function hasSaveableChanges()
    {
        return Globals::$allowSavingReportComment || $this->warning_log_id || parent::hasSaveableChanges();
    }

    /**
     * @return mixed|string|string[]|null
     */
    public function getViewableUsername()
    {
        return \XF::phrase('svReport.content_reporter')->render();
    }

    /**
     * @return \XF\Entity\User|User
     */
    public function getViewableUser()
    {
        if (!$this->is_report)
        {
            return $this->User;
        }

        $visitor = \XF::visitor();
        if (($visitor->user_id && $this->User->user_id === $visitor->user_id) ||
            $this->Report->canViewReporter($error))
        {
            return $this->User;
        }

        /** @var \XF\Repository\User $userRepo */
        $userRepo = $this->repository('XF:User');

        return $userRepo->getGuestUser($this->ViewableUsername);
    }

    /**
     * @return int|null
     */
    protected function getCurrentReportQueueId()
    {
        $report = $this->Report;

        return $report && $report->offsetExists('queue_id')
            ? $report->get('queue_id')
            : null;
    }

    /**
     * @return string|null
     */
    protected function getCurrentReportQueueName()
    {
        $report = $this->Report;

        return $report && $report->offsetExists('queue_name')
            ? $report->get('queue_name')
            : null;
    }

    public function getDeferredId()
    {
        return $this->_getDeferredValue(function() {
            return $this->report_comment_id;
        },'save');
    }

    protected function _postSave()
    {
        parent::_postSave();

        if ($this->Report && $this->Report->last_modified_date <= $this->comment_date)
        {
            $this->Report->fastUpdate('last_modified_id', $this->report_comment_id);
            $this->Report->hydrateRelation('LastModified', $this);
        }

        if ($this->Report && $this->Report->first_report_date > $this->comment_date)
        {
            $this->Report->fastUpdate('first_report_date', $this->comment_date);
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

            /** @var ReportComment $lastReportComment */
            $lastReportComment = $lastReportCommentFinder->fetchOne();
            if ($lastReportComment)
            {
                $this->Report->fastUpdate('last_modified_id', $lastReportComment->report_comment_id);
            }
        }
    }

    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        if (!$structure->contentType)
        {
            $structure->contentType = 'report_comment';
        }

        $structure->columns['warning_log_id'] = ['type' => self::UINT, 'default' => null, 'nullable' => true];
        $structure->columns['alertSent'] = ['type' => self::BOOL, 'default' => false];
        $structure->columns['alertComment'] = ['type' => self::STR, 'default' => null, 'nullable' => true];
        $structure->columns['assigned_user_id'] = ['type' => self::UINT, 'default' => null, 'nullable' => true];
        $structure->columns['assigned_username'] = ['type' => self::STR, 'maxLength' => 50, 'default' => ''];

        $structure->behaviors['XF:Reactable'] = ['stateField' => ''];
        $structure->behaviors['XF:Indexable'] = [
            'checkForUpdates' => ['message', 'user_id', 'report_id', 'comment_date', 'state_change', 'is_report'],
        ];
        $structure->getters['ViewableUsername'] = true;
        $structure->getters['ViewableUser'] = true;
        $structure->relations['Reactions'] = [
            'entity'     => 'XF:ReactionContent',
            'type'       => self::TO_MANY,
            'conditions' => [
                ['content_type', '=', 'report_comment'],
                ['content_id', '=', '$report_comment_id'],
            ],
            'key'        => 'reaction_user_id',
            'order'      => 'reaction_date',
        ];
        $structure->relations['WarningLog'] = [
            'entity'     => 'SV\ReportImprovements:WarningLog',
            'type'       => self::TO_ONE,
            'conditions' => 'warning_log_id',
            'primary'    => true,
        ];
        $structure->relations['AssignedUser'] = [
            'entity'     => 'XF:User',
            'type'       => self::TO_ONE,
            'conditions' => [['user_id', '=', '$assigned_user_id']],
            'primary'    => true,
        ];

        $structure->defaultWith[] = 'WarningLog';
        $structure->defaultWith[] = 'WarningLog.Warning';

        static::addReactableStructureElements($structure);

        // compat fix for Report Centre Essentials < v2.4.0
        $addonsCache = \XF::app()->container('addon.cache');
        if (isset($addonsCache['SV/ReportCentreEssentials']) && $addonsCache['SV/ReportCentreEssentials'] < 2040000)
        {
            $structure->getters['queue_id'] = ['getter' => 'getCurrentReportQueueId', 'cache' => false];
            $structure->getters['queue_name'] = ['getter' => 'getCurrentReportQueueName', 'cache' => false];
        }

        return $structure;
    }
}