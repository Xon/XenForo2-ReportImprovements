<?php

namespace SV\ReportImprovements\XF\Pub\Controller;

use SV\ReportImprovements\Globals;
use XF\Mvc\ParameterBag;

/**
 * Class Warning
 * Extends \XF\Pub\Controller\Warning
 *
 * @package SV\ReportImprovements\XF\Pub\Controller
 */
class Warning extends XFCP_Warning
{
    /**
     * @param int   $id
     * @param array $extraWith
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
     * @return \XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\Reroute
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionDelete(ParameterBag $params)
    {
        /** @var \SV\ReportImprovements\XF\Entity\Warning $warning */
        $warning = $this->assertViewableWarning($params->warning_id);
        $report = $warning->Report;

        if ($this->request()->exists('resolve_report') &&
            $this->filter('resolve_report', 'bool'))
        {
            Globals::$resolveWarningReport = !$report || $report->canView() && $report->canUpdate($error);
        }

        return parent::actionDelete($params);
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Redirect
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionExpire(ParameterBag $params)
    {
        /** @var \SV\ReportImprovements\XF\Entity\Warning $warning */
        $warning = $this->assertViewableWarning($params->warning_id);
        $report = $warning->Report;

        if ($this->request()->exists('resolve_report') &&
            $this->filter('resolve_report', 'bool'))
        {
            Globals::$resolveWarningReport = !$report || $report->canView() && $report->canUpdate($error);
        }

        return parent::actionExpire($params);
    }
}