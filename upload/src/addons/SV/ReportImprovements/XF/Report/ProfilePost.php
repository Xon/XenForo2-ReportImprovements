<?php

namespace SV\ReportImprovements\XF\Report;

use SV\ReportImprovements\Report\ContentInterface;
use XF\Entity\Report;
use XF\Mvc\Entity\Entity;

/**
 * Class ProfilePost
 * @extends \XF\Report\ProfilePost
 *
 * @package SV\ReportImprovements\XF\Report
 */
class ProfilePost extends XFCP_ProfilePost implements ContentInterface
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

        return $visitor->canViewProfilePostReport($report);
    }

    /**
     * @param Report                        $report
     * @param Entity|\XF\Entity\ProfilePost $content
     */
    public function setupReportEntityContent(Report $report, Entity $content)
    {
        parent::setupReportEntityContent($report, $content);

        $contentInfo = $report->content_info;
        $contentInfo['post_date'] = $content->post_date;
        $report->content_info = $contentInfo;
    }

    public function getContentDate(Report $report): ?int
    {
        $contentDate = $report->content_info['post_date'] ?? null;
        if ($contentDate === null)
        {
            /** @var \XF\Entity\ProfilePost|null $content */
            $content = $report->getContent();
            if ($content === null)
            {
                return null;
            }

            $contentInfo = $report->content_info;
            $contentInfo['post_date'] = $contentDate = $content->post_date;
            $report->fastUpdate('content_info', $contentInfo);
        }

        return $contentDate;
    }
}