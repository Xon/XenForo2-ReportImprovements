<?php

namespace SV\ReportImprovements\XF\ControllerPlugin;

use SV\ReportImprovements\XF\Service\Report\Creator as ExtendedReportCreatorService;
use SV\StandardLib\Helper;
use XF\ControllerPlugin\Editor as EditorPlugin;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Reply\Exception as ReplyException;

/**
 * @extends \XF\ControllerPlugin\Report
 */
class Report extends XFCP_Report
{
    /**
     * @param string $contentType
     * @param Entity $content
     * @return ExtendedReportCreatorService
     * @throws ReplyException
     * @noinspection PhpMissingReturnTypeInspection
     */
    protected function setupReportCreate($contentType, Entity $content)
    {
        if (\XF::options()->svRichTextReport ?? false)
        {
            $editorPlugin = Helper::plugin($this, EditorPlugin::class);
            $message = $editorPlugin->fromInput('message');
            // if the editorPlugin is called again, 'message' is used before 'message_html'
            $this->request->set('message', $message);
        }

        /** @var ExtendedReportCreatorService $creator */
        $creator = parent::setupReportCreate($contentType, $content);

        $creator->logIp(true);

        return $creator;
    }
}