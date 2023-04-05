<?php

namespace SV\ReportImprovements\XF\Entity;

use SV\ReportImprovements\Repository\ReportQueue as ReportQueueRepo;
use XF\Mvc\Entity\Structure;
use function assert;

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
     * @param Report                 $report
     * @param \XF\Phrase|String|null $error
     * @return bool
     * @noinspection PhpUnusedParameterInspection
     */
    public function canViewConversationMessageReport(Report $report, &$error = null): bool
    {
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        return $report->hasReportPermission('viewReportConversation');
    }

    /**
     * @param Report                 $report
     * @param \XF\Phrase|String|null $error
     * @return bool
     */
    public function canViewProfilePostCommentReport(Report $report, &$error = null): bool
    {
        return $this->canViewProfilePostReport($report,$error);
    }

    /**
     * @param Report                 $report
     * @param \XF\Phrase|String|null $error
     * @return bool
     * @noinspection PhpUnusedParameterInspection
     */
    public function canViewProfilePostReport(Report $report, &$error = null): bool
    {
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        return $report->hasReportPermission('viewReportProfilePost');
    }

    /**
     * @param Report                 $report
     * @param \XF\Phrase|String|null $error
     * @return bool
     * @noinspection PhpUnusedParameterInspection
     */
    public function canViewUserReport(Report $report, &$error = null): bool
    {
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        return $report->hasReportPermission('viewReportUser');
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

        return $this->hasPermission('report_queue', 'viewReporterUsername');
    }

    /**
     * @param \XF\Phrase|String|null $error
     * @return bool
     * @noinspection PhpUnusedParameterInspection
     */
    public function canReportFromApprovalQueue(&$error = null): bool
    {
        $visitor = \XF::visitor();
        if (!$visitor->user_id)
        {
            return false;
        }

        if (!$visitor->hasPermission('general', 'report'))
        {
            return false;
        }

        return true;
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
            $this->hasPermission('report_queue', 'updateReport');
    }

    protected function _postSave()
    {
        parent::_postSave();

        if ($this->wasCanBeAssignedReports)
        {
            $doRebuild = $this->isChanged(['user_state', 'is_moderator', 'permission_combination_id']);
            // check for permission change
            if ($this->isChanged(['user_group_id', 'secondary_group_ids']))
            {
                $newPermissions = \XF::permissionCache()->getPermissionSet($this->permission_combination_id);
                if (!$newPermissions->hasGlobalPermission('general', 'viewReports') ||
                    !$newPermissions->hasGlobalPermission('report_queue', 'updateReport'))
                {
                    $doRebuild = true;
                }
            }

            if ($doRebuild)
            {
                $reportQueueRepo = \XF::repository('SV\ReportImprovements:ReportQueue');
                assert($reportQueueRepo instanceof ReportQueueRepo);
                $reportQueueRepo->resetNonModeratorsWhoCanHandleReportCacheLater();
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