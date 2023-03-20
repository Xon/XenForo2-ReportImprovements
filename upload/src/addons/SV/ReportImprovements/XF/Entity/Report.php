<?php

namespace SV\ReportImprovements\XF\Entity;

use SV\ReportImprovements\Globals;
use SV\SearchImprovements\Search\Features\ISearchableDiscussionUser;
use SV\SearchImprovements\Search\Features\ISearchableReplyCount;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use function array_key_exists;
use function assert;

/**
 * Class Report
 * Extends \XF\Entity\Report
 *
 * @package SV\ReportImprovements\XF\Entity
 * COLUMNS
 * @property int                $last_modified_id
 * @property int|null           $assigned_date
 * @property int|null           $assigner_user_id
 * GETTERS
 * @property-read string        $title_string
 * @property-read string        $username
 * @property-read array         $commenter_user_ids
 * @property-read array         $comment_ids
 * @property-read ReportComment $LastModified
 * @property-read ?int          $content_date
 * RELATIONS
 * @property-read User|null     $AssignerUser
 * @property-read ReportComment $LastModified_
 */
class Report extends XFCP_Report implements ISearchableReplyCount, ISearchableDiscussionUser
{
    public function canView()
    {
        /** @var User $visitor */
        $visitor = \XF::visitor();

        if ($visitor->user_id === 0)
        {
            return false;
        }

        if (!$visitor->canViewReports())
        {
            return false;
        }

        if (!$this->hasReportPermission('viewReports'))
        {
            return false;
        }

        return parent::canView();
    }

    /**
     * @param \XF\Phrase|String|null $error
     * @return bool
     * @noinspection PhpUnusedParameterInspection
     */
    public function canComment(&$error = null): bool
    {
        /** @var User $visitor */
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        if ($this->isClosed())
        {
            return $this->hasReportPermission('replyReportClosed');
        }

        return $this->hasReportPermission('replyReport');
    }

    /**
     * @param \XF\Phrase|String|null $error
     * @return bool
     * @noinspection PhpUnusedParameterInspection
     */
    public function canUpdate(&$error = null): bool
    {
        /** @var User $visitor */
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        if ($this->assigned_user_id === $visitor->user_id)
        {
            return true;
        }

        return $this->hasReportPermission('updateReport');
    }

    /**
     * @param \XF\Phrase|String|null $error
     * @return bool
     * @noinspection PhpUnusedParameterInspection
     */
    public function canAssign(&$error = null): bool
    {
        /** @var User $visitor */
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        return $this->hasReportPermission('assignReport');
    }

    public function canJoinConversation(): bool
    {
        if ($this->content_type !== 'conversation_message')
        {
            return false;
        }

        /** @var User $visitor */
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        return $visitor->hasPermission('conversation', 'joinReported');
    }

    /**
     * @param \XF\Phrase|String|null $error
     * @return bool
     */
    public function canViewReporter(&$error = null): bool
    {
        /** @var User $visitor */
        $visitor = \XF::visitor();

        return $visitor->canViewReporter($error);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function canViewAttachments(&$error = null): bool
    {
        $visitor = \XF::visitor();
        if ($visitor->user_id === 0)
        {
            return false;
        }

        if (!$this->hasReportPermission('viewAttachment'))
        {
            return false;
        }

        return true;
    }


    public function canUploadAndManageAttachments(): bool
    {
        $visitor = \XF::visitor();

        if ($visitor->user_id === 0)
        {
            return false;
        }

        if (!$this->hasReportPermission('uploadAttachment'))
        {
            return false;
        }

        return true;
    }

    public function canUploadVideos(): bool
    {
        $visitor = \XF::visitor();

        if ($visitor->user_id === 0)
        {
            return false;
        }

        $options = $this->app()->options();

        if (empty($options->allowVideoUploads['enabled']))
        {
            return false;
        }

        if (!$this->hasReportPermission('uploadVideo'))
        {
            return false;
        }

        return true;
    }

    /**
     * @param string $permission
     * @return bool|int
     */
    public function hasReportPermission(string $permission)
    {
        $reportQueueId = (int)($this->queue_id ?? 0);

        /** @var User $visitor */
        $visitor = \XF::visitor();

        if ($reportQueueId !== 0)
        {
            // When Report Centre Essentials is installed, the per-queue view permission is changed
            if ($permission === 'viewReports')
            {
                $permission = 'view';
            }

            return $visitor->hasContentPermission('report_queue', $reportQueueId, $permission);
        }

        // content permissions are collapsed into a flat array, but general permissions are not
        $group = $permission === 'viewReports' ? 'general' : 'report_queue';

        return $visitor->hasPermission($group, $permission);
    }

    public function getBreadcrumbs(bool $includeSelf = true)
    {
        $breadcrumbs = [];

        if ($includeSelf)
        {
            $breadcrumbs[] = [
                'value' => $this->title,
                'href'  => \XF::app()->router()->buildLink('reports', $this),
            ];
        }

        return $breadcrumbs;
    }

    public function getContentDate(): ?int
    {
        $handler = $this->Handler;

        if (!($handler instanceof \SV\ReportImprovements\Report\ContentInterface))
        {
            return null;
        }

        return $handler->getContentDate($this);
    }

    public function setContent(Entity $content = null)
    {
        $this->_valueCache['Content'] = $content;
    }

    public function getMessage(): string
    {
        $handler = $this->Handler;

        return $handler ? $handler->getContentMessage($this) : $this->title;
    }

    /**
     * @return array
     */
    public function getLastModifiedCache()
    {
        $return = parent::getLastModifiedCache();

        $return['modified_id'] = $this->last_modified_id;

        return $return;
    }

    /**
     * @return ReportComment|null
     */
    public function getLastModified()
    {
        if ($this->last_modified_id === 0)
        {
            $reportCommentFinder = $this->finder('XF:ReportComment');
            $reportCommentFinder->where('report_id', $this->report_id);
            $reportCommentFinder->order('comment_date', 'DESC');
            $reportCommentFinder->with($this->getCommentWith());
            /** @var ReportComment $reportComment */
            $reportComment = $reportCommentFinder->fetchOne();

            if ($reportComment)
            {
                $this->fastUpdate('last_modified_id', $reportComment->report_comment_id);
                $this->hydrateRelation('LastModified', $reportComment);
            }
        }
        else if (!\array_key_exists('LastModified', $this->_relations))
        {
            $finder = $this->getRelationFinder('LastModified');
            $finder->with($this->getCommentWith());
            $reportComment = $finder->fetchOne();
        }
        else
        {
            $reportComment = $this->LastModified_;
        }

        return $reportComment;
    }

    /**
     * @return int[]
     */
    public function getCommentIds(): array
    {
        return $this->db()->fetchAllColumn('
			SELECT report_comment_id
			FROM xf_report_comment
			WHERE report_id = ?
			ORDER BY comment_date
		', $this->report_id);
    }

    /**
     * @return int[]
     */
    public function getCommenterUserIds(): array
    {
        return
            $this->db()->fetchAllColumn('
              SELECT DISTINCT user_id
              FROM xf_report_comment
              WHERE report_id = ?
        ', $this->report_id);
    }

    /**
     * @return int[]
     */
    public function getDiscussionUserIds(): array
    {
        $userIds = $this->commenter_user_ids;
        $userIds[] = $this->content_user_id;
        $userIds[] = $this->assigner_user_id;
        $userIds[] = $this->assigned_user_id;

        return $userIds;
    }

    /**
     * @return string[]
     */
    protected function getCommentWith(): array
    {
        $with = ['User', 'User.Profile', 'User.Privacy'];
        if ($userId = \XF::visitor()->user_id)
        {
            if (\XF::options()->showMessageOnlineStatus)
            {
                $with[] = 'User.Activity';
            }

            $with[] = 'Reactions|' . $userId;
        }

        if ($this->content_type === 'post')
        {
            $with[] = 'WarningLog.ReplyBan';
        }

        return $with;
    }

    public function getCommentsFinder(): \XF\Mvc\Entity\Finder
    {
        $direction = (\XF::app()->options()->sv_reverse_report_comment_order ?? false) ? 'DESC' : 'ASC';

        $finder = $this->finder('XF:ReportComment')
                       ->where('report_id', $this->report_id)
                       ->order('comment_date', $direction);

        $finder->with($this->getCommentWith());

        return $finder;
    }

    public function getComments(): \XF\Mvc\Entity\AbstractCollection
    {
        return $this->getCommentsFinder()->fetch();
    }

    /**
     * @return string
     * @noinspection PhpMissingReturnTypeInspection
     */
    protected function getUsername()
    {
        if (\is_callable([parent::class,'getUsername']))
        {
            /** @noinspection PhpUndefinedMethodInspection */
            return parent::getUsername();
        }

        if ($this->User)
        {
            return $this->User->username;
        }

        if (isset($this->content_info['username']))
        {
            return $this->content_info['username'];
        }

        if (isset($this->content_info['user']['username']))
        {
            return $this->content_info['user']['username'];
        }

        return '';
    }

    public function getTitle()
    {
        try
        {
            return parent::getTitle();
        }
        catch (\Exception $e)
        {
            \XF::logException($e, false, 'Error accessing title for report ('.$this->report_id.')');
            return '';
        }
    }

    public function getRelationFinder($key, $type = 'current')
    {
        $finder = parent::getRelationFinder($key, $type);
        if (Globals::$shimCommentsFinder && $key === 'Comments')
        {
            $finder->whereImpossible();
        }

        return $finder;
    }

    protected function getTitleString(): string
    {
        /** @var \XF\Phrase|string|mixed $value */
        $value = $this->title;

        if ($value instanceof \XF\Phrase)
        {
            return $value->render('raw');
        }

        return \strval($value);
    }

    public function getReplyCountForSearch(): int
    {
        // do not consider the report count, since that doesn't signal much that is useful
        return $this->comment_count;
    }

    public function svDisableIndexing(): void
    {
        $this->getBehaviors();
    }

    public function svEnableIndexing(): void
    {
        $this->getBehaviors();
        if (!array_key_exists('XF:IndexableContainer', $this->_behaviors))
        {
            $class = \XF::extendClass(\XF\Behavior\IndexableContainer::class);

            $behavior = new $class($this, $this->structure()->behaviors['XF:IndexableContainer']);
            assert($behavior instanceof \XF\Behavior\IndexableContainer);
            $behavior->onSetup();

            $this->_behaviors['XF:IndexableContainer'] = $behavior;
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
            $structure->contentType = 'report';
        }

        $structure->behaviors['XF:Indexable'] = [
            'checkForUpdates' => ['content_user_id', 'content_info', 'first_report_date', 'report_state'],
        ];
        $structure->behaviors['XF:IndexableContainer'] = [
            'childContentType' => 'report_comment',
            'childIds'         => function ($report) { return $report->comment_ids; },
            'checkForUpdates'  => ['report_id', 'is_report', 'report_state', 'assigned_user_id', 'assigned_date', 'assigner_user_id'],
        ];

        $structure->columns['assigned_date'] = ['type' => self::UINT, 'default' => null, 'nullable' => true];
        $structure->columns['assigner_user_id'] = ['type' => self::UINT, 'default' => null, 'nullable' => true];
        $structure->columns['last_modified_id'] = ['type' => self::UINT, 'default' => 0];

        $structure->getters['username'] = ['getter' => 'getUsername', 'cache' => true];
        $structure->getters['content_date'] = ['getter' => 'getContentDate', 'cache' => true];
        $structure->getters['message'] = ['getter' => 'getMessage', 'cache' => true];
        $structure->getters['commenter_user_ids'] = ['getter' => 'getCommenterUserIds', 'cache' => true];
        $structure->getters['comment_ids'] = ['getter' => 'getCommentIds', 'cache' => true];
        $structure->getters['LastModified'] = ['getter' => 'getLastModified', 'cache' => true];
        $structure->getters['Comments'] = ['getter' => 'getComments', 'cache' => true];
        $structure->getters['title_string'] = ['getter' => 'getTitleString', 'cache' => true];

        $structure->relations['LastModified'] = [
            'entity'     => 'XF:ReportComment',
            'type'       => self::TO_ONE,
            'conditions' => [
                ['report_comment_id', '=', '$last_modified_id'],
            ],
            'primary'    => true,
        ];

        $structure->relations['AssignerUser'] = [
            'entity' => 'XF:User',
            'type' => self::TO_ONE,
            'conditions' => [
                ['user_id', '=', '$assigner_user_id']
            ],
            'primary' => true
        ];

        $addOns = \XF::app()->container('addon.cache');
        if (isset($addOns['SV/ReportCentreEssentials']))
        {
            $contentId = '$queue_id';
        }
        else
        {
            $contentId = '0';
        }

        $structure->relations['Permissions'] = [
            'entity' => 'XF:PermissionCacheContent',
            'type' => self::TO_MANY,
            'conditions' => [
                ['content_type', '=', 'report_queue'],
                ['content_id', '=', $contentId]
            ],
            'key' => 'permission_combination_id',
            'proxy' => true
        ];

        if (\SV\SearchImprovements\Globals::repo()->isUsingElasticSearch())
        {
            $structure->behaviors['XF:IndexableContainer']['checkForUpdates'][] = 'report_count';
            $structure->behaviors['XF:IndexableContainer']['checkForUpdates'][] = 'comment_count';
        }

        return $structure;
    }
}