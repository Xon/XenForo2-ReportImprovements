<?php

namespace SV\ReportImprovements\XF\ControllerPlugin;

use SV\ReportImprovements\XF\Service\Report\Creator;
use XF\Mvc\Entity\Entity;

/**
 * Extends \XF\ControllerPlugin\Report
 */
class Report extends XFCP_Report
{
    protected function setupReportCreate($contentType, Entity $content)
    {
        /** @var Creator $creator */
        $creator = parent::setupReportCreate($contentType, $content);

        $creator->logIp(true);

        return $creator;
    }
}