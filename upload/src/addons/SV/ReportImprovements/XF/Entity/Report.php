<?php

namespace SV\ReportImprovements\XF\Entity;

/**
 * Class Report
 *
 * Extends \XF\Entity\Report
 *
 * @package SV\ReportImprovements\XF\Entity
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
}