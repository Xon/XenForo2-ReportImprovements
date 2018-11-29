<?php

namespace SV\ReportImprovements\XF\Report;

use SV\ReportImprovements\Report\ContentInterface;
use XF\Entity\Report;
use XF\Mvc\Entity\Entity;

/**
 * Class ConversationMessage
 *
 * Extends \XF\Report\ConversationMessage
 *
 * @package SV\ReportImprovements\XF\Report
 */
class ConversationMessage extends XFCP_ConversationMessage implements ContentInterface
{
    /**
     * @param Report $report
     *
     * @return bool
     */
    public function canView(Report $report)
    {
        /** @var \SV\ReportImprovements\XF\Entity\User $visitor */
        $visitor = \XF::visitor();

        return $visitor->canViewConversationMessageReport();
    }

    /**
     * @param Report $report
     * @param Entity|\XF\Entity\ConversationMessage $content
     */
    public function setupReportEntityContent(Report $report, Entity $content)
    {
        parent::setupReportEntityContent($report, $content);

        $contentInfo = $report->content_info;
        $contentInfo['message_date'] = $content->message_date;
        $report->content_info = $contentInfo;
    }

    /**
     * @param Report $report
     *
     * @return int
     */
    public function getContentDate(Report $report)
    {
        if (!isset($report->content_info['message_date']))
        {
            /** @var \XF\Entity\ConversationMessage $content $content */
            $content = $report->getContent();
            if (!$content)
            {
                return 0;
            }

            $contentInfo = $report->content_info;
            $contentInfo['message_date'] = $content->message_date;
            $report->fastUpdate('content_info', $contentInfo);
        }

        return $report->content_info['message_date'];
    }
}