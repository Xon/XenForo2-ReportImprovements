<?php

namespace SV\ReportImprovements\XF\Pub\Controller;

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
     * @param ParameterBag $parameterBag
     *
     * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\Reroute
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionResolveReport(ParameterBag $parameterBag)
    {
        $this->assertPostOnly();

        /** @var \SV\ReportImprovements\XF\Entity\Warning $warning */
        $warning = $this->assertViewableWarning($parameterBag->warning_id);
        if (!$report = $warning->Report)
        {
            throw $this->exception($this->notFound(\XF::phrase('requested_report_not_found')));
        }

        if (!$report->canUpdate($error) || $report->isClosed())
        {
            throw $this->exception($this->noPermission($error));
        }

        if ($this->filter('resolve', 'bool'))
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

            return $this->redirect($this->getDynamicRedirect());
        }

        return $this->rerouteController('XF:Warning', 'index', $parameterBag->params());
    }
}