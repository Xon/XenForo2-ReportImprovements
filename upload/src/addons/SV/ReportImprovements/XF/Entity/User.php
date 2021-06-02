<?php

namespace SV\ReportImprovements\XF\Entity;

use XF\Mvc\Entity\Structure;

/**
 * Class User
 * Extends \XF\Entity\User
 *
 * @package SV\ReportImprovements\XF\Entity
 */
class User extends XFCP_User
{
    /**
     * @param \XF\Phrase|String|null $error
     * @return bool
     * @noinspection PhpUnusedParameterInspection
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function canViewReports(&$error = null)
    {
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        return $visitor->hasPermission('general', 'viewReports');
    }

    /**
     * @return bool
     */
    public function canReportSearch(): bool
    {
        if (!$this->getOption('reportSearch') || !$this->canSearch())
        {
            return false;
        }

        return $this->canViewReports();
    }

    /**
     * @param \XF\Phrase|String|null $error
     * @return bool
     * @noinspection PhpUnusedParameterInspection
     */
    public function canViewConversationMessageReport(&$error = null): bool
    {
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        return $visitor->hasPermission('conversation', 'viewReportConversation');
    }

    /**
     * @param \XF\Phrase|String|null $error
     * @return bool
     */
    public function canViewProfilePostCommentReport(&$error = null): bool
    {
        return $this->canViewProfilePostReport($error);
    }

    /**
     * @param \XF\Phrase|String|null $error
     * @return bool
     * @noinspection PhpUnusedParameterInspection
     */
    public function canViewProfilePostReport(&$error = null): bool
    {
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        return $visitor->hasPermission('profilePost', 'viewReportProfilePost');
    }

    /**
     * @param \XF\Phrase|String|null $error
     * @return bool
     * @noinspection PhpUnusedParameterInspection
     */
    public function canViewUserReport(&$error = null): bool
    {
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        return $visitor->hasPermission('general', 'viewReportUser');
    }

    /**
     * @param int  $nodeId
     * @param \XF\Phrase|String|null $error
     * @return bool
     * @noinspection PhpUnusedParameterInspection
     */
    public function canViewPostReport(int $nodeId, &$error = null): bool
    {
        if (!$this->hasNodePermission($nodeId, 'viewReportPost'))
        {
            return false;
        }

        if (\XF::options()->sv_moderators_respect_view_node ?? false)
        {
            // just check forum permission instead of loading the entire forum to avoid N+1 queries
            if (!$this->hasNodePermission($nodeId, 'view'))
            {
                return false;
            }
        }

        return true;
    }

    /**
     * @param \XF\Phrase|String|null $error
     * @return bool
     * @noinspection PhpUnusedParameterInspection
     */
    public function canViewReporter(&$error = null): bool
    {
        if (!$this->user_id)
        {
            return false;
        }

        return $this->hasPermission('general', 'viewReporterUsername');
    }

    protected $wasCanBeAssignedReports = false;

    protected function _preSave()
    {
        parent::_preSave();

        $this->wasCanBeAssignedReports =
            (
                !$this->is_moderator && $this->getPreviousValue('is_moderator') ||
                $this->is_moderator && !$this->getPreviousValue('is_moderator')
            ) &&
            $this->hasPermission('general', 'viewReports') &&
            $this->hasPermission('general', 'updateReport');
    }

    protected function _postSave()
    {
        parent::_postSave();

        if ($this->wasCanBeAssignedReports)
        {
            $doRebuild = $this->isChanged('user_state') || $this->isChanged('is_moderator');
            // check for permission change
            if ($this->isChanged(['user_group_id', 'secondary_group_ids']))
            {
                $newPermissions = \XF::permissionCache()->getPermissionSet($this->permission_combination_id);
                if (!$newPermissions->hasGlobalPermission('general', 'viewReports') ||
                    !$newPermissions->hasGlobalPermission('general', 'updateReport'))
                {
                    $doRebuild = true;
                }
            }

            if ($doRebuild)
            {
                /** @var \SV\ReportImprovements\XF\Repository\Report $repo */
                $repo = \XF::repository('XF:Report');
                $repo->deferResetNonModeratorsWhoCanHandleReportCache();
            }
        }
    }

    /**
     * @param Structure $structure
     * @return Structure
     * @noinspection PhpMissingReturnTypeInspection
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->options['reportSearch'] = true;

        return $structure;
    }
}