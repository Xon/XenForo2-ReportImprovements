<?php

namespace SV\ReportImprovements\XF\Report;

use SV\ReportImprovements\Report\ContentInterface;
use SV\ReportImprovements\XF\Entity\Report as ExtendedReportEntity;
use SV\ReportImprovements\XF\Entity\User as ExtendedUserEntity;
use XF\Entity\Report as ReportEntity;
use XF\Mvc\Entity\Entity;

/**
 * @extends \XF\Report\ProfilePost
 */
class ProfilePost extends XFCP_ProfilePost implements ContentInterface
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

        return $visitor->canViewProfilePostReport($report);
    }

    /**
     * @param ReportEntity                  $report
     * @param Entity|\XF\Entity\ProfilePost $content
     */
    public function setupReportEntityContent(ReportEntity $report, Entity $content)
    {
        parent::setupReportEntityContent($report, $content);

        $contentInfo = $report->content_info;
        $contentInfo['post_date'] = $content->post_date;
        $report->content_info = $contentInfo;
    }

    public function getReportedContentDate(ReportEntity $report): ?int
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