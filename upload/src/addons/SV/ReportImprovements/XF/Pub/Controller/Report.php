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

    /**
     * @param \XF\Entity\Report|\SV\ReportImprovements\XF\Entity\Report $report
     *
     * @return \XF\Mvc\Reply\Error|\XF\Service\Report\Commenter
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function setupReportComment(\XF\Entity\Report $report)
    {
        if (!$report->canUpdate($error))
        {
            // pls no funny business kthxbai :smile:
            if ($this->request()->exists('report_state')
                || $this->request()->exists('send_alert')
                || $this->request()->exists('alert_comment')
            )
            {
                throw $this->exception($this->noPermission($error));
            }
        }

        $selfAssignUnassign = $this->filter('self_assign_unassign', 'bool');
        if ($selfAssignUnassign)
        {
            /** @var \SV\ReportImprovements\XF\Entity\User $visitor */
            $visitor = \XF::visitor();
            $reportState = 'assigned';

            if ($report->report_state === 'assigned')
            {
                if ($report->assigned_user_id !== $visitor->user_id && !$report->canAssign($error))
                {
                    throw $this->exception($this->noPermission($error));
                }
                $reportState = 'open';
            }

            $this->request()->set('report_state', $reportState);
        }

        if (
            !$report->canComment($error)
            && ($this->request()->exists('message') || $this->request()->exists('message_html'))
        )
        {
            throw $this->exception($this->noPermission($error));
        }

        if (!$selfAssignUnassign && !$report->canComment() && !$report->canUpdate())
        {
            throw $this->exception(
                $this->error(\XF::phrase('svReportImprov_please_assign_or_unassign_the_report_item'))
            );
        }

        return parent::setupReportComment($report);
    }

    /**
     * @param ParameterBag $params
     *
     * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionReassign(ParameterBag $params)
    {
        $this->assertPostOnly();

        /** @var \SV\ReportImprovements\XF\Entity\Report $report */
        $report = $this->assertViewableReport($params->report_id);
        if (!$report->canAssign($error))
        {
            return $this->noPermission($error);
        }

        return parent::actionReassign($params);
    }
}