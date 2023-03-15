<?php

namespace SV\ReportImprovements\XF\Report;

use SV\ReportImprovements\Report\ContentInterface;
use SV\ReportImprovements\Report\ReportSearchFormInterface;
use SV\ReportImprovements\XF\Entity\Thread;
use XF\Entity\Report;
use XF\Mvc\Entity\Entity;
use XF\Search\MetadataStructure;
use function assert;

/**
 * Class Post
 * Extends \XF\Report\Post
 *
 * @package SV\ReportImprovements\XF\Report
 */
class Post extends XFCP_Post implements ContentInterface, ReportSearchFormInterface
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
        /** @var Thread|\SV\MultiPrefix\XF\Entity\Thread $thread */
        $thread = $content->Thread;
        $contentInfo['prefix_id'] = $thread->sv_prefix_ids ?? $thread->prefix_id;
        $report->content_info = $contentInfo;
    }

    public function getContentDate(Report $report): ?int
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
        return [
            'nodeTree' => $this->getSearchableNodeTree()
        ];
    }

    protected function getSearchableNodeTree(): \XF\Tree
    {
        /** @var \XF\Repository\Node $nodeRepo */
        $nodeRepo = \XF::repository('XF:Node');
        $nodeTree = $nodeRepo->createNodeTree($nodeRepo->getNodeList());

        // only list nodes that are forums or contain forums
        /** @noinspection PhpUnnecessaryLocalVariableInspection */
        $nodeTree = $nodeTree->filter(null, function(int $id, \XF\Entity\Node $node, int $depth, array $children, \XF\Tree $tree): bool
        {
            return ($children || $node->node_type_id == 'Forum');
        });

        return $nodeTree;
    }

    public function applySearchTypeConstraintsFromInput(\XF\Search\Query\Query $query, \XF\Http\Request $request, array $urlConstraints): void
    {
        $handler = \XF::app()->search()->handler($this->contentType);
        assert($handler instanceof \XF\Search\Data\Post);
        $handler->applyTypeConstraintsFromInput($query, $request, $urlConstraints);
    }

    public function setupMetadataStructure(MetadataStructure $structure): void
    {
        $handler = \XF::app()->search()->handler($this->contentType);
        assert($handler instanceof \XF\Search\Data\Post);
        $handler->setupMetadataStructure($structure);
    }

    public function populateMetaData(\XF\Entity\Report $entity, array &$metaData): void
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