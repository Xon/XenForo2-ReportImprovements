<?php

namespace SV\ReportImprovements\XF\Pub\Controller;

use SV\ReportImprovements\Globals;
use XF\Mvc\ParameterBag;

/**
 * Class Warning
 * 
 * Extends \XF\Pub\Controller\Warning
 *
 * @package SV\ReportImprovements\XF\Pub\Controller
 */
class Warning extends XFCP_Warning
{
    /**
     * @param int $id
     * @param array $extraWith
     *
     * @return \XF\Entity\Warning
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertViewableWarning($id, array $extraWith = [])
    {
        $extraWith[] = 'Report';
        return parent::assertViewableWarning($id, $extraWith);
    }

    /**
     * @param ParameterBag $params
     *
     * @return \XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\Reroute
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionDelete(ParameterBag $params)
    {
        /** @var \SV\ReportImprovements\XF\Entity\Warning $warning */
        $warning = $this->assertViewableWarning($params->warning_id);
        $report = $warning->Report;

        $response = parent::actionDelete($params);

        if ($response instanceof \XF\Mvc\Reply\Redirect)
        {
            if ($this->request()->exists('resolve_report') && $this->filter('resolve_report', 'bool') === true)
            {
                if (!$report->canUpdate($error) || $report->isClosed())
                {
                    throw $this->exception($this->noPermission($error));
                }

                $this->resolveReport($report);
            }
        }

        return $response;
    }

    /**
     * @param ParameterBag $params
     *
     * @return \XF\Mvc\Reply\Redirect
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionExpire(ParameterBag $params)
    {
        /** @var \SV\ReportImprovements\XF\Entity\Warning $warning */
        $warning = $this->assertViewableWarning($params->warning_id);
        $report = $warning->Report;

        $response = parent::actionDelete($params);

        if ($response instanceof \XF\Mvc\Reply\Reroute)
        {
            if ($this->request()->exists('resolve_report') && $this->filter('resolve_report', 'bool') === true)
            {
                if (!$report->canUpdate($error) || $report->isClosed())
                {
                    throw $this->exception($this->noPermission($error));
                }

                $this->resolveReport($report);
            }
        }

        return $response;
    }

    /**
     * @param \XF\Entity\Report $report
     *
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function resolveReport(\XF\Entity\Report $report)
    {
        /** @var \XF\Service\Report\Commenter $commenter */
        $commenter = $this->service('XF:Report\Commenter', $report);
        $commenter->setReportState('resolved');
        if (!$commenter->validate($errors))
        {
            throw $this->exception($this->error($errors));
        }
        $commenter->save();
        $commenter->sendNotifications();

        $report = $commenter->getReport();
        $report->draft_comment->delete();

        $this->session()->reportLastRead = \XF::$time;
    }
}