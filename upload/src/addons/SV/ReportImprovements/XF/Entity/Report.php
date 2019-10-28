<?php

namespace SV\ReportImprovements\XF\Entity;

use SV\ReportImprovements\Globals;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * Class Report
 * Extends \XF\Entity\Report
 *
 * @package SV\ReportImprovements\XF\Entity
 * COLUMNS
 * @property int           last_modified_id
 * GETTERS
 * @property array         commenter_user_ids
 * @property array         comment_ids
 * @property ReportComment LastModified
 * RELATIONS
 * @property ReportComment LastModified_
 */
class Report extends XFCP_Report
{
    public function canView()
    {
        /** @var User $visitor */
        $visitor = \XF::visitor();

        if (!$visitor->user_id ||
            !$visitor->canViewReports())
        {
            return false;
        }

        return parent::canView();
    }

    /**
     * @param null $error
     * @return bool
     */
    public function canComment(/** @noinspection PhpUnusedParameterInspection */
        &$error = null)
    {
        /** @var User $visitor */
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        if ($this->isClosed())
        {
            return $visitor->hasPermission('general', 'replyReportClosed');
        }

        return $visitor->hasPermission('general', 'replyReport');
    }

    /**
     * @param null $error
     * @return bool
     */
    public function canUpdate(/** @noinspection PhpUnusedParameterInspection */
        &$error = null)
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

        return $visitor->hasPermission('general', 'updateReport');
    }

    /**
     * @param null $error
     * @return bool
     */
    public function canAssign(/** @noinspection PhpUnusedParameterInspection */
        &$error = null)
    {
        /** @var User $visitor */
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        return $visitor->hasPermission('general', 'assignReport');
    }

    public function canJoinConversation()
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
     * @param null $error
     * @return bool
     */
    public function canViewReporter(&$error = null)
    {
        /** @var User $visitor */
        $visitor = \XF::visitor();

        return $visitor->canViewReporter($error);
    }

    /**
     * @param bool $includeSelf
     * @return array
     */
    public function getBreadcrumbs($includeSelf = true)
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

    /**
     * @return int|null
     */
    public function getContentDate()
    {
        $handler = $this->Handler;

        if (!$handler instanceof \SV\ReportImprovements\Report\ContentInterface)
        {
            return 0;
        }

        return $handler ? $handler->getContentDate($this) : 0;
    }

    /**
     * @param Entity $content
     */
    public function setContent(Entity $content = null)
    {
        $this->_valueCache['Content'] = $content;
    }

    /**
     * @return string
     */
    public function getMessage()
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
     * @return ReportComment
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
     * @return array
     */
    public function getCommentIds()
    {
        return $this->db()->fetchAllColumn('
			SELECT report_comment_id
			FROM xf_report_comment
			WHERE report_id = ?
			ORDER BY comment_date
		', $this->report_id);
    }

    /**
     * @return array
     */
    public function getCommenterUserIds()
    {
        return array_keys(
            $this->db()->fetchAllKeyed('
              SELECT DISTINCT user_id
              FROM xf_report_comment AS report_comment
              WHERE report_comment.report_id = ?
        ', 'user_id', $this->report_id)
        );
    }

    protected function getCommentWith()
    {
        $with = ['User', 'User.Profile', 'User.Privacy'];
        if ($userId = \XF::visitor()->user_id)
        {
            if (\XF::options()->showMessageOnlineStatus)
            {
                $with[] = 'User.Activity';
            }

            $with[] = 'Likes|' . $userId;
        }

        if ($this->content_type === 'post')
        {
            $with[] = 'WarningLog.ReplyBan';
        }

        return $with;
    }

    public function getCommentsFinder()
    {
        $direction = \XF::app()->options()->sv_reverse_report_comment_order ? 'DESC' : 'ASC';

        $finder = $this->finder('XF:ReportComment')
                       ->where('report_id', $this->report_id)
                       ->order('comment_date', $direction);

        $finder->with($this->getCommentWith());

        return $finder;
    }

    public function getComments()
    {
        return $this->getCommentsFinder()->fetch();
    }

    public function getRelationFinder($key, $type = 'current')
    {
        if (Globals::$shimCommentsFinder && $key === 'Comments')
        {
            return $this->getCommentsFinder();
        }

        return parent::getRelationFinder($key, $type);
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
            'checkForUpdates'  => ['report_id', 'is_report'],
        ];

        $structure->columns['last_modified_id'] = ['type' => self::UINT, 'default' => 0];

        $structure->getters['content_date'] = true;
        $structure->getters['message'] = true;
        $structure->getters['commenter_user_ids'] = true;
        $structure->getters['comment_ids'] = true;
        $structure->getters['LastModified'] = true;
        $structure->getters['Comments'] = true;
        $structure->getters['LastModified'] = true;

        $structure->relations['LastModified'] = [
            'entity'     => 'XF:ReportComment',
            'type'       => self::TO_ONE,
            'conditions' => [
                ['report_comment_id', '=', '$last_modified_id'],
            ],
            'primary'    => true,
        ];

        return $structure;
    }
}