<?php

namespace SV\ReportImprovements\XF\ControllerPlugin;

use SV\ReportImprovements\XF\Service\Report\Creator;
use XF\ControllerPlugin\Editor as EditorPlugin;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Reply\Exception as ReplyException;

/**
 * Extends \XF\ControllerPlugin\Report
 */
class Report extends XFCP_Report
{
    /**
     * @param string $contentType
     * @param Entity $content
     * @return Creator
     * @throws ReplyException
     * @noinspection PhpMissingReturnTypeInspection
     */
    protected function setupReportCreate($contentType, Entity $content)
    {
        if (\XF::options()->svRichTextReport ?? false)
        {
            /** @var EditorPlugin $editorPlugin */
            $editorPlugin = $this->plugin('XF:Editor');
            $message = $editorPlugin->fromInput('message');
            // if the editorPlugin is called again, 'message' is used before 'message_html'
            $this->request->set('message', $message);
        }

        /** @var Creator $creator */
        $creator = parent::setupReportCreate($contentType, $content);

        $creator->logIp(true);

        return $creator;
    }
}