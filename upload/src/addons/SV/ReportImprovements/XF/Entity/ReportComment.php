<?php

namespace SV\ReportImprovements\XF\Entity;

use SV\ReportImprovements\Entity\WarningLog;
use SV\ReportImprovements\Enums\ReportType;
use SV\ReportImprovements\Globals;
use SV\ReportImprovements\XF\Entity\ReportComment as ReportCommentEntity;
use SV\StandardLib\Helper;
use XF\Entity\Attachment;
use XF\Entity\ContainableInterface;
use XF\Entity\ContainableTrait;
use XF\Entity\DatableInterface;
use XF\Entity\DatableTrait;
use XF\Entity\ReactionContent;
use XF\Entity\ReactionTrait;
use XF\Entity\User as UserEntity;
use XF\Entity\ViewableInterface;
use XF\Mvc\Entity\AbstractCollection as AbstractCollection;
use XF\Mvc\Entity\Structure;
use XF\Phrase;

/**
 * Class ReportComment
 * @extends \XF\Entity\ReportComment
 *
 * @package SV\ReportImprovements\XF\Entity
 * COLUMNS
 * @property int                                  $reaction_score
 * @property array                                $reactions_
 * @property array                                $reaction_users_
 * @property int|null                             $warning_log_id
 * @property bool                                 $alertSent
 * @property string|null                          $alertComment
 * @property int|null                             $assigned_user_id
 * @property string                               $assigned_username
 * @property int                                  $attach_count
 * @property array|null                           $embed_metadata
 * @property int                                  $edit_count
 * @property int                                  $last_edit_user_id
 * @property int                                  $last_edit_date
 * @property int|null                             $ip_id
 * GETTERS
 * @property-read array                           $Unfurls
 * @property-read string                          $ViewableUsername
 * @property-read User                            $ViewableUser
 * @property mixed                                $reactions
 * @property mixed                                $reaction_users
 * RELATIONS
 * @property-read AbstractCollection|Attachment[] $Attachments
 * @property-read ReactionContent[]               $Reactions
 * @property-read WarningLog|null                 $WarningLog
 * @property-read Report|null                     $Report
 * @property-read User|null                       $User
 */
class ReportComment extends XFCP_ReportComment implements ViewableInterface, DatableInterface, ContainableInterface
{
    use ReactionTrait;
    use ContainableTrait;
    use DatableTrait;

    public function getContentContainerType(): string
    {
        return 'report';
    }

    public function getContentContainerIdColumn(): string
    {
        return 'report_id';
    }

    public function getContentDateColumn(): string
    {
        return 'comment_date';
    }

    public function canView()
    {
        if ($this->Report === null)
        {
            return false;
        }

        return $this->Report->canView();
    }

    /**
     * @param Phrase|String|null $error
     * @return bool
     */
    public function canEdit(&$error = null): bool
    {
        $visitor = \XF::visitor();

        if ($visitor->user_id === 0)
        {
            return false;
        }

        if ($this->hasReportPermission('editAny'))
        {
            return true;
        }

        if ($this->user_id === $visitor->user_id && $this->hasReportPermission('edit'))
        {
            $editLimit = (int)$this->hasReportPermission('editOwnPostTimeLimit');
            if ($editLimit !== -1 && ($editLimit === 0 || $this->comment_date < \XF::$time - 60 * $editLimit))
            {
                $error = \XF::phraseDeferred('message_edit_time_limit_expired', ['minutes' => $editLimit]);
                return false;
            }

            if (!$this->Report->canComment($error))
            {
                return false;
            }

            return true;
        }

        return false;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function canViewHistory(&$error = null): bool
    {
        $visitor = \XF::visitor();
        if ($visitor->user_id === 0)
        {
            return false;
        }

        if (!$this->app()->options()->editHistory['enabled'])
        {
            return false;
        }

        return $this->canEdit();
    }

    /**
     * @param Phrase|String|null $error
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

        return $this->hasReportPermission('reportReact');
    }

    public function getReportStateChange(): Phrase
    {
        return $this->Report->getReportState($this->state_change);
    }

    /**
     * @param string $permission
     * @return bool|int
     */
    public function hasReportPermission(string $permission)
    {
        if (!$this->Report)
        {
            return false;
        }

        return $this->Report->hasReportPermission($permission);
    }

    public function isAttachmentEmbedded($attachmentId): bool
    {
        if (!$this->embed_metadata)
        {
            return false;
        }

        if ($attachmentId instanceof Attachment)
        {
            $attachmentId = $attachmentId->attachment_id;
        }

        return isset($this->embed_metadata['attachments'][$attachmentId]);
    }

    public function getBbCodeRenderOptions($context, $type)
    {
        $options = parent::getBbCodeRenderOptions($context, $type);

        if ($this->is_report)
        {
            $options['attachments'] = 0;
            $options['viewAttachments'] = false;
            $options['unfurls'] = [];
            if (\XF::options()->svDisableEmbedsInUserReports ?? true)
            {
                $options['allowUnfurl'] = false;
                $options['shortenUrl'] = false;
                $options['svDisableUrlMediaTag'] = true;
            }
        }
        else
        {
            $options['attachments'] = $this->attach_count ? $this->Attachments : [];
            $options['viewAttachments'] = $this->Report && $this->Report->canViewAttachments();
            $options['unfurls'] = $this->Unfurls ?? [];
        }

        return $options;
    }

    public function getUnfurls(): array
    {
        return $this->_getterCache['Unfurls'] ?? [];
    }

    public function setUnfurls(?array $unfurls = null)
    {
        $this->_getterCache['Unfurls'] = $unfurls;
    }

    /**
     * @return bool
     */
    public function hasSaveableChanges()
    {
        return Globals::$forceSavingReportComment || $this->warning_log_id || parent::hasSaveableChanges();
    }

    /**
     * @return mixed|string|string[]|null
     */
    public function getViewableUsername()
    {
        return \XF::phrase('svReport.content_reporter')->render();
    }

    /**
     * @return UserEntity|User
     */
    public function getViewableUser()
    {
        if ($this->User === null)
        {
            $username = $this->Report->canViewReporter($error)
                ? $this->username
                : $this->ViewableUsername;

            /** @var \XF\Repository\User $userRepo */
            $userRepo = $this->repository('XF:User');

            return $userRepo->getGuestUser($username);
        }

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

    public function getReportType(): string
    {
        if ($this->warning_log_id)
        {
            if ($this->WarningLog !== null)
            {
                if ($this->WarningLog->reply_ban_thread_id)
                {
                    return ReportType::Reply_ban;
                }

                if (Helper::isAddOnActive('SV/ForumBan') && $this->WarningLog->reply_ban_node_id)
                {
                    return ReportType::Forum_ban;
                }
            }

            return ReportType::Warning;
        }
        else if ($this->is_report)
        {
            return ReportType::User_report;
        }

        return ReportType::Comment;
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

    public function getBreadcrumbs() : array
    {
        /** @var ReportCommentEntity $content */
        return $this->Report->getBreadcrumbs();
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

        if ($this->isUpdate() && $this->getOption('log_moderator'))
        {
            $this->app()->logger()->logModeratorChanges('report_comment', $this);
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

        $structure->columns['username']['noIndex'] = true;
        $structure->columns['warning_log_id'] = ['type' => self::UINT, 'default' => null, 'nullable' => true];
        $structure->columns['alertSent'] = ['type' => self::BOOL, 'default' => false];
        $structure->columns['alertComment'] = ['type' => self::STR, 'default' => null, 'nullable' => true];
        $structure->columns['assigned_user_id'] = ['type' => self::UINT, 'default' => null, 'nullable' => true];
        $structure->columns['assigned_username'] = ['type' => self::STR, 'maxLength' => 50, 'default' => '', 'noIndex' => true];
        // edit support
        // Pre-XF2.2.9 bug, where UINT assumes 32bit value, so explicitly set 'max'
        $structure->columns['ip_id'] = ['type' => self::UINT, 'max' => \PHP_INT_MAX, 'nullable' => true, 'default' => null];
        $structure->columns['attach_count']   = ['type' => self::UINT, 'max' => 65535, 'default' => 0];
        $structure->columns['embed_metadata'] = ['type' => self::JSON_ARRAY, 'nullable' => true, 'default' => null];
        $structure->columns['last_edit_date'] = ['type' => self::UINT, 'default' => 0];
        $structure->columns['last_edit_user_id'] = ['type' => self::UINT, 'default' => 0];
        $structure->columns['edit_count'] = ['type' => self::UINT, 'forced' => true, 'default' => 0];

        $structure->behaviors['XF:Reactable'] = ['stateField' => ''];
        $structure->behaviors['XF:Indexable'] = [
            'checkForUpdates' => ['message', 'user_id', 'report_id', 'comment_date', 'state_change', 'is_report'],
        ];
        $structure->getters['ViewableUsername'] = ['getter' => 'getViewableUsername', 'cache' => true];
        $structure->getters['ViewableUser'] = ['getter' => 'getViewableUser', 'cache' => true];
        $structure->getters['Unfurls'] = ['getter' => 'getUnfurls', 'cache' => true];
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
        $structure->relations['Attachments'] = [
            'entity'     => 'XF:Attachment',
            'type'       => self::TO_MANY,
            'conditions' => [
                ['content_type', '=', 'report_comment'],
                ['content_id', '=', '$report_comment_id']
            ],
            'with'       => 'Data',
            'order'      => 'attach_date'
        ];
        $structure->options['log_moderator'] = false;

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