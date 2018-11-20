<?php

namespace SV\ReportImprovements\XF\Entity;

/**
 * Class User
 *
 * Extends \XF\Entity\User
 *
 * @package SV\ReportImprovements\XF\Entity
 */
class User extends XFCP_User
{
    /**
     * @param null $error
     *
     * @return bool
     */
    public function canViewReports(/** @noinspection PhpUnusedParameterInspection */
        &$error = null)
    {
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        return $visitor->hasPermission('general', 'viewReports');
    }

    /**
     * @param null $error
     *
     * @return bool
     */
    public function canViewConversationMessageReport(/** @noinspection PhpUnusedParameterInspection */
        &$error = null)
    {
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        return $visitor->hasPermission('general', 'viewReportConversation');
    }

    /**
     * @param null $error
     *
     * @return bool
     */
    public function canViewProfilePostCommentReport(&$error = null)
    {
        return $this->canViewProfilePostReport($error);
    }

    /**
     * @param null $error
     *
     * @return bool
     */
    public function canViewProfilePostReport(/** @noinspection PhpUnusedParameterInspection */
        &$error = null)
    {
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        return $visitor->hasPermission('general', 'viewReportProfilePost');
    }

    /**
     * @param null $error
     *
     * @return bool
     */
    public function canViewUserReport(/** @noinspection PhpUnusedParameterInspection */
        &$error = null)
    {
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        return $visitor->hasPermission('general', 'viewReportUser');
    }
}