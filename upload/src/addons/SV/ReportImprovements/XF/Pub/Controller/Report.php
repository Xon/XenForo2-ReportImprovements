<?php

namespace SV\ReportImprovements\XF\Pub\Controller;

use XF\Mvc\ParameterBag;

/**
 * Class Report
 *
 * Extends \XF\Pub\Controller\Report
 *
 * @package SV\ReportImprovements\XF\Pub\Controller
 */
class Report extends XFCP_Report
{
    /**
     * @param              $action
     * @param ParameterBag $params
     *
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function preDispatchController($action, ParameterBag $params)
    {
        parent::preDispatchController($action, $params);

        /** @var \SV\ReportImprovements\XF\Entity\User $visitor */
        $visitor = \XF::visitor();
        if (!$visitor->canViewReports($error))
        {
            throw $this->exception($this->noPermission($error));
        }
    }
}