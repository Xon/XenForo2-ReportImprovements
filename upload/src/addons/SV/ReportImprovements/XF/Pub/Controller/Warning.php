<?php

namespace SV\ReportImprovements\XF\Pub\Controller;

use SV\ReportImprovements\XF\ControllerPlugin\Warn as WarnPlugin;
use SV\StandardLib\Helper;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception as ReplyException;

/**
 * Class Warning
 * @extends \XF\Pub\Controller\Warning
 *
 * @package SV\ReportImprovements\XF\Pub\Controller
 */
class Warning extends XFCP_Warning
{
    /**
     * @param int   $id
     * @param array $extraWith
     * @return \XF\Entity\Warning
     * @throws ReplyException
     */
    protected function assertViewableWarning($id, array $extraWith = [])
    {
        $extraWith[] = 'Report';

        return parent::assertViewableWarning($id, $extraWith);
    }

    /**
     * @param ParameterBag $params
     * @return AbstractReply
     * @throws ReplyException
     */
    public function actionDelete(ParameterBag $params)
    {
        /** @var \SV\ReportImprovements\XF\Entity\Warning $warning */
        /** @noinspection PhpUndefinedFieldInspection */
        $warning = $this->assertViewableWarning($params->warning_id);

        /** @var WarnPlugin $warnPlugin */
        $warnPlugin = Helper::plugin($this, \XF\ControllerPlugin\Warn::class);
        $warnPlugin->resolveReportFor($warning);

        return parent::actionDelete($params);
    }

    /**
     * @param ParameterBag $params
     * @return AbstractReply
     * @throws ReplyException
     */
    public function actionExpire(ParameterBag $params)
    {
        /** @var \SV\ReportImprovements\XF\Entity\Warning $warning */
        /** @noinspection PhpUndefinedFieldInspection */
        $warning = $this->assertViewableWarning($params->warning_id);

        /** @var WarnPlugin $warnPlugin */
        $warnPlugin = Helper::plugin($this, \XF\ControllerPlugin\Warn::class);
        $warnPlugin->resolveReportFor($warning);

        return parent::actionExpire($params);
    }
}