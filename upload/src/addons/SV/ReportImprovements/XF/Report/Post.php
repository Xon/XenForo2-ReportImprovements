<?php

namespace SV\ReportImprovements\XF\Report;

use SV\ReportImprovements\Report\ContentInterface;
use XF\Entity\Report;
use XF\Mvc\Entity\Entity;

/**
 * Class Post
 * Extends \XF\Report\Post
 *
 * @package SV\ReportImprovements\XF\Report
 */
class Post extends XFCP_Post implements ContentInterface
{
    /**
     * @param Report $report
     * @return bool
     */
    public function canView(Report $report)
    {
        /** @var \SV\ReportImprovements\XF\Entity\User $visitor */
        $visitor = \XF::visitor();

        return $visitor->canViewPostReport($report->content_info['node_id']);
    }

    /**
     * @param Report                                       $report
     * @param Entity|\SV\ReportImprovements\XF\Entity\Post $content
     */
    public function setupReportEntityContent(Report $report, Entity $content)
    {
        parent::setupReportEntityContent($report, $content);

        $contentInfo = $report->content_info;
        $contentInfo['post_date'] = $content->post_date;
        $report->content_info = $contentInfo;
    }

    /**
     * @param Report $report
     * @return int
     */
    public function getContentDate(Report $report)
    {
        if (!isset($report->content_info['post_date']))
        {
            /** @var \XF\Entity\Post $content $content */
            $content = $report->getContent();
            if (!$content)
            {
                return 0;
            }

            $contentInfo = $report->content_info;
            $contentInfo['post_date'] = $content->post_date;
            $report->fastUpdate('content_info', $contentInfo);
        }

        return $report->content_info['post_date'];
    }

    public function getContentLink(Report $report)
    {
        $reportInfo = $report->content_info;
        if ($reportInfo && !isset($reportInfo['post_id']))
        {
            // XF1 => XF2 conversion bug
            $reportInfo['post_id'] = $report->content_id;
            $report->content_info = $reportInfo;
        }

        return parent::getContentLink($report);
    }
}