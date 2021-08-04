<?php

namespace SV\ReportImprovements\XF\Report;

use SV\ReportImprovements\Report\ContentInterface;
use XF\Entity\Report;
use XF\Mvc\Entity\Entity;

/**
 * Class ProfilePostComment
 * Extends \XF\Report\ProfilePostComment
 *
 * @package SV\ReportImprovements\XF\Report
 */
class ProfilePostComment extends XFCP_ProfilePostComment implements ContentInterface
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

        return $visitor->canViewProfilePostCommentReport($report);
    }

    /**
     * @param Report                               $report
     * @param Entity|\XF\Entity\ProfilePostComment $content
     */
    public function setupReportEntityContent(Report $report, Entity $content)
    {
        parent::setupReportEntityContent($report, $content);

        $contentInfo = $report->content_info;
        $contentInfo['comment_date'] = $content->comment_date;
        $report->content_info = $contentInfo;
    }

    /**
     * @param Report $report
     * @return int
     */
    public function getContentDate(Report $report)
    {
        if (!isset($report->content_info['comment_date']))
        {
            /** @var \XF\Entity\ProfilePostComment $content $content */
            $content = $report->getContent();
            if (!$content)
            {
                return 0;
            }

            $contentInfo = $report->content_info;
            $contentInfo['comment_date'] = $content->comment_date;
            $report->fastUpdate('content_info', $contentInfo);
        }

        return $report->content_info['comment_date'];
    }
}