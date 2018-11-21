<?php

namespace SV\ReportImprovements\XF\ControllerPlugin;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Reply\View;

/**
 * Class Warn
 * 
 * Extends \XF\ControllerPlugin\Warn
 *
 * @package SV\ReportImprovements\XF\ControllerPlugin
 */
class Warn extends XFCP_Warn
{
    /**
     * @param string $contentType
     * @param Entity $content
     * @param string $warnUrl
     * @param array  $breadcrumbs
     *
     * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|View
     */
    public function actionWarn($contentType, Entity $content, $warnUrl, array $breadcrumbs = [])
    {
        $response = parent::actionWarn($contentType, $content, $warnUrl, $breadcrumbs);

        if ($response instanceof View)
        {
            /** @var \XF\Entity\Report $contentReport */
            $contentReport = $this->finder('XF:Report')
                ->where('content_type', $contentType)
                ->where('content_id', $content->getEntityId())
                ->with(['LastModified', 'LastModifiedUser'])
                ->fetchOne();

            $response->setParam('report', $contentReport);
        }

        return $response;
    }
}