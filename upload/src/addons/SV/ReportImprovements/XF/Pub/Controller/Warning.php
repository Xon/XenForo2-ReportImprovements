<?php

namespace SV\ReportImprovements\XF\Pub\Controller;

use SV\ReportImprovements\XF\ControllerPlugin\Warn as ExtendedWarnPlugin;
use SV\ReportImprovements\XF\Entity\Warning as ExtendedWarningEntity;
use SV\StandardLib\Helper;
use XF\ControllerPlugin\Warn as WarnPlugin;
use XF\Entity\Warning as WarningEntity;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception as ReplyException;

/**
 * @extends \XF\Pub\Controller\Warning
 */
class Warning extends XFCP_Warning
{
    /**
     * @param int   $id
     * @param array $extraWith
     * @return WarningEntity
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
        /** @var ExtendedWarningEntity $warning */
        /** @noinspection PhpUndefinedFieldInspection */
        $warning = $this->assertViewableWarning($params->warning_id);

        /** @var ExtendedWarnPlugin $warnPlugin */
        $warnPlugin = Helper::plugin($this, WarnPlugin::class);
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
        /** @var ExtendedWarningEntity $warning */
        /** @noinspection PhpUndefinedFieldInspection */
        $warning = $this->assertViewableWarning($params->warning_id);

        /** @var ExtendedWarnPlugin $warnPlugin */
        $warnPlugin = Helper::plugin($this, WarnPlugin::class);
        $warnPlugin->resolveReportFor($warning);

        return parent::actionExpire($params);
    }
}