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
 * GETTERS
 * @property \SV\ReportImprovements\XF\Entity\User ViewableUsername
 * @property \SV\ReportImprovements\XF\Entity\User ViewableUser
 */
class Report extends XFCP_Report
{
    /**
     * @param null $error
     *
     * @return bool
     */
    public function canReply(/** @noinspection PhpUnusedParameterInspection */
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
        if (!$this->canViewReporter($error))
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

        $structure->getters['ViewableUsername'] = true;
        $structure->getters['ViewableUser'] = true;

        return $structure;
    }
}