<?php

namespace SV\ReportImprovements\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

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
     * @param null $error
     *
     * @return bool
     */
    public function canViewConversationMessageReport(&$error = null)
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
    public function canViewProfilePostReport(&$error = null)
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
    public function canViewProfilePostCommentReport(&$error = null)
    {
        return $this->canViewProfilePostReport($error);
    }

    /**
     * @param null $error
     *
     * @return bool
     */
    public function canViewUserReport(&$error = null)
    {
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        return $visitor->hasPermission('general', 'viewReportUser');
    }
}