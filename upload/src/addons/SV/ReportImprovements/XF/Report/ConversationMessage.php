<?php

namespace SV\ReportImprovements\XF\Report;

use SV\ReportImprovements\Report\ContentInterface;
use XF\Entity\Report;
use XF\Mvc\Entity\Entity;

/**
 * Class ConversationMessage
 * @extends \XF\Report\ConversationMessage
 *
 * @package SV\ReportImprovements\XF\Report
 */
class ConversationMessage extends XFCP_ConversationMessage implements ContentInterface
{
    /**
     * @param Report $report
     * @return bool
     */
    public function canView(Report $report)
    {
        /** @var \SV\ReportImprovements\XF\Entity\Report $report */
        /** @var \SV\ReportImprovements\XF\Entity\User $visitor */
        $visitor = \XF::visitor();

        return $visitor->canViewConversationMessageReport($report);
    }

    /**
     * @param Report                                $report
     * @param Entity|\XF\Entity\ConversationMessage $content
     */
    public function setupReportEntityContent(Report $report, Entity $content)
    {
        parent::setupReportEntityContent($report, $content);

        $contentInfo = $report->content_info;
        $contentInfo['message_date'] = $content->message_date;
        $report->content_info = $contentInfo;
    }

    public function getReportedContentDate(Report $report): ?int
    {
        $contentDate = $report->content_info['message_date'] ?? null;
        if ($contentDate === null)
        {
            /** @var \XF\Entity\ConversationMessage|null $content */
            $content = $report->getContent();
            if ($content === null)
            {
                return null;
            }

            $contentInfo = $report->content_info;
            $contentInfo['message_date'] = $contentDate = $content->message_date;
            $report->fastUpdate('content_info', $contentInfo);
        }

        return $contentDate;
    }

    public function getContentLink(Report $report)
    {
        $url = parent::getContentLink($report);
        if (!$url)
        {
            /** @var \XF\Entity\ConversationMessage $message */
            $message = $report->Content;
            if ($message && $message->canView())
            {
                $url = \XF::app()->router()->buildLink('conversations/messages', $message);
            }
        }

        return $url;
    }
}