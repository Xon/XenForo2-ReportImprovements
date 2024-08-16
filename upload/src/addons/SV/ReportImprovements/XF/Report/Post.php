<?php

namespace SV\ReportImprovements\XF\Report;

use SV\MultiPrefix\XF\Entity\Thread;
use SV\ReportImprovements\Report\ContentInterface;
use SV\ReportImprovements\Report\ReportSearchFormInterface;
use SV\ReportImprovements\XF\Entity\Thread as ExtendedThreadEntity;
use XF\Entity\Report;
use XF\Http\Request;
use XF\Mvc\Entity\Entity;
use XF\Search\Data\Post as PostSearch;
use XF\Search\MetadataStructure;
use XF\Search\Query\Query;
use function assert;

/**
 * @extends \XF\Report\Post
 */
class Post extends XFCP_Post implements ContentInterface, ReportSearchFormInterface
{
    /**
     * @var PostSearch|null
     */
    protected $searchHandler = null;

    protected function getSearchHandler(): PostSearch
    {
        if ($this->searchHandler === null)
        {
            $handler = \XF::app()->search()->handler($this->contentType);
            assert($handler instanceof PostSearch);
            $this->searchHandler = $handler;
        }

        return $this->searchHandler;
    }

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
        /** @var ExtendedThreadEntity|Thread $thread */
        $thread = $content->Thread;
        $contentInfo['prefix_id'] = $thread->sv_prefix_ids ?? $thread->prefix_id;
        $report->content_info = $contentInfo;
    }

    public function getReportedContentDate(Report $report): ?int
    {
        $contentDate = $report->content_info['post_date'] ?? null;
        if ($contentDate === null)
        {
            /** @var \XF\Entity\Post|null $content */
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

    public function getContentLink(Report $report)
    {
        $reportInfo = $report->content_info;
        if ($reportInfo && !isset($reportInfo['post_id']))
        {
            // XF1 => XF2 conversion bug
            $reportInfo['post_id'] = $report->content_id;
            $report->setTrusted('content_info', $reportInfo);
        }

        return parent::getContentLink($report);
    }

    public function getSearchFormTemplate(): string
    {
        return 'public:search_form_report_comment_post';
    }

    public function getSearchFormData(): array
    {
        return $this->getSearchHandler()->getSearchFormData();
    }

    public function applySearchTypeConstraintsFromInput(Query $query, Request $request, array $urlConstraints): void
    {
        $this->getSearchHandler()->applyTypeConstraintsFromInput($query, $request, $urlConstraints);
    }

    public function setupMetadataStructure(MetadataStructure $structure): void
    {
        $this->getSearchHandler()->setupMetadataStructure($structure);
    }

    public function populateMetaData(Report $entity, array &$metaData): void
    {
        // see setupReportEntityContent for attributes cached on the report
        $threadId = $entity->content_info['thread_id'] ?? null;
        if ($threadId !== null)
        {
            $metaData['thread'] = $threadId;
        }

        $nodeId = $entity->content_info['node_id'] ?? null;
        if ($nodeId !== null)
        {
            $metaData['node'] = $nodeId;
        }

        $prefixId = $entity->content_info['prefix_id'] ?? null;
        if ($prefixId !== null)
        {
            $metaData['prefix'] = $prefixId;
        }
    }
}