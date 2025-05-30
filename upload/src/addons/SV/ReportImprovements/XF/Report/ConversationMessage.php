<?php

namespace SV\ReportImprovements\XF\Report;

use SV\ReportImprovements\Report\ContentInterface;
use SV\ReportImprovements\XF\Entity\Report as ExtendedReportEntity;
use SV\ReportImprovements\XF\Entity\User as ExtendedUserEntity;
use XF\Entity\ConversationMessage as ConversationMessageEntity;
use XF\Entity\Report as ReportEntity;
use XF\Mvc\Entity\Entity;

/**
 * @extends \XF\Report\ConversationMessage
 */
class ConversationMessage extends XFCP_ConversationMessage implements ContentInterface
{
    /**
     * @param ReportEntity $report
     * @return bool
     */
    public function canView(ReportEntity $report)
    {
        /** @var ExtendedReportEntity $report */
        /** @var ExtendedUserEntity $visitor */
        $visitor = \XF::visitor();

        return $visitor->canViewConversationMessageReport($report);
    }

    /**
     * @param ReportEntity                     $report
     * @param Entity|ConversationMessageEntity $content
     */
    public function setupReportEntityContent(ReportEntity $report, Entity $content)
    {
        parent::setupReportEntityContent($report, $content);

        $contentInfo = $report->content_info;
        $contentInfo['message_date'] = $content->message_date;
        $report->content_info = $contentInfo;
    }

    public function getReportedContentDate(ReportEntity $report): ?int
    {
        $contentDate = $report->content_info['message_date'] ?? null;
        if ($contentDate === null)
        {
            /** @var ConversationMessageEntity|null $content */
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

    public function getContentLink(ReportEntity $report)
    {
        $url = parent::getContentLink($report);
        if (!$url)
        {
            /** @var ConversationMessageEntity $message */
            $message = $report->Content;
            if ($message && $message->canView())
            {
                $url = \XF::app()->router()->buildLink('conversations/messages', $message);
            }
        }

        return $url;
    }
}