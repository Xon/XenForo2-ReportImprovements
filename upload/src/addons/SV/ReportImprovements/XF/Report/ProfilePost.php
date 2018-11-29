<?php

namespace SV\ReportImprovements\XF\Report;

use SV\ReportImprovements\Report\ContentInterface;
use XF\Entity\Report;
use XF\Mvc\Entity\Entity;

/**
 * Class ProfilePost
 *
 * Extends \XF\Report\ProfilePost
 *
 * @package SV\ReportImprovements\XF\Report
 */
class ProfilePost extends XFCP_ProfilePost implements ContentInterface
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

        return $visitor->canViewProfilePostReport();
    }

    /**
     * @param Report $report
     * @param Entity|\XF\Entity\ProfilePost $content
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
     *
     * @return int
     */
    public function getContentDate(Report $report)
    {
        if (!isset($report->content_info['post_date']))
        {
            /** @var \XF\Entity\ProfilePost $content $content */
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
}