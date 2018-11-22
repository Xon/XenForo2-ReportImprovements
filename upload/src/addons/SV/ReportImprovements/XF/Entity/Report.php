<?php

namespace SV\ReportImprovements\XF\Entity;

use XF\Mvc\Entity\Structure;

/**
 * Class Report
 *
 * Extends \XF\Entity\Report
 *
 * @package SV\ReportImprovements\XF\Entity
 *
 * COLUMNS
 * @property int last_modified_id
 *
 * GETTERS
 * @property array commenter_user_ids
 * @property array comment_ids
 * @property \SV\ReportImprovements\XF\Entity\User ViewableUsername
 * @property \SV\ReportImprovements\XF\Entity\User ViewableUser
 * @property \SV\ReportImprovements\XF\Entity\ReportComment LastModified
 */
class Report extends XFCP_Report
{
    /**
     * @param null $error
     *
     * @return bool
     */
    public function canComment(/** @noinspection PhpUnusedParameterInspection */
        &$error = null)
    {
        /** @var \SV\ReportImprovements\XF\Entity\User $visitor */
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
     *
     * @return bool
     */
    public function canUpdate(/** @noinspection PhpUnusedParameterInspection */
        &$error = null)
    {
        /** @var \SV\ReportImprovements\XF\Entity\User $visitor */
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
     *
     * @return bool
     */
    public function canAssign(/** @noinspection PhpUnusedParameterInspection */
        &$error = null)
    {
        /** @var \SV\ReportImprovements\XF\Entity\User $visitor */
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        return $visitor->hasPermission('general', 'assignReport');
    }

    /**
     * @param null $error
     *
     * @return bool
     */
    public function canViewReporter(/** @noinspection PhpUnusedParameterInspection */
        &$error = null)
    {
        /** @var \SV\ReportImprovements\XF\Entity\User $visitor */
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        return $visitor->hasPermission('general', 'viewReporterUsername');
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
        if ($this->canViewReporter($error))
        {
            return $this->User;
        }

        /** @var \XF\Repository\User $userRepo */
        $userRepo = $this->repository('XF:User');
        return $userRepo->getGuestUser($this->ViewableUsername);
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

            /** @var ReportComment $reportComment */
            $reportComment = $reportCommentFinder->fetchOne();

            if ($reportComment)
            {
                $this->fastUpdate('last_modified_id', $reportComment->report_comment_id);
                $this->hydrateRelation('LastModified', $reportComment);
            }
        }

        return $this->getRelation('LastModified');
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

    /**
     * @param Structure $structure
     *
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns['last_modified_id'] = ['type' => self::UINT, 'default' => 0];

        $structure->getters['commenter_user_ids'] = true;
        $structure->getters['comment_ids'] = true;
        $structure->getters['ViewableUsername'] = true;
        $structure->getters['ViewableUser'] = true;
        $structure->getters['LastModified'] = true;

        $structure->relations['LastModified'] = [
            'entity' => 'XF:ReportComment',
            'type' => self::TO_ONE,
            'conditions' => [
                ['report_comment_id', '=', '$last_modified_id']
            ],
            'primary' => true
        ];

        return $structure;
    }
}