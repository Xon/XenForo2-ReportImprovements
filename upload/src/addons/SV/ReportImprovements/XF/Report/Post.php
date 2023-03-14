<?php

namespace SV\ReportImprovements\XF\Report;

use SV\ReportImprovements\Report\ContentInterface;
use SV\ReportImprovements\Report\ReportSearchFormInterface;
use SV\SearchImprovements\Util\Arr;
use XF\Entity\Report;
use XF\Mvc\Entity\Entity;
use function array_fill_keys;
use function array_keys;
use function array_unique;
use function array_values;
use function count;
use function in_array;
use function is_callable;

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
        $threadId = (int)$request->filter('c.thread', 'uint');
        if ($threadId !== 0)
        {
            $query->withMetadata('thread', $threadId);

            if (is_callable([$query, 'inTitleOnly']))
            {
                $query->inTitleOnly(false);
            }
        }
        else
        {
            Arr::unsetUrlConstraint($urlConstraints, 'c.thread');

            $nodeIds = $request->filter('c.nodes', 'array-uint');
            $nodeIds = array_values(array_unique($nodeIds));
            if (count($nodeIds) !== 0 && !in_array(0, $nodeIds, true))
            {
                if ($request->filter('c.child_nodes', 'bool'))
                {
                    /** @var \XF\Repository\Node $nodeRepo */
                    $nodeRepo = \XF::repository('XF:Node');
                    $nodeTree = $nodeRepo->createNodeTree($nodeRepo->getFullNodeListWithTypeData()->filterViewable());

                    $searchNodeIds = array_fill_keys($nodeIds, true);
                    $nodeTree->traverse(function (int $id, \XF\Entity\Node $node) use (&$searchNodeIds): void {
                        if (isset($searchNodeIds[$id]) || isset($searchNodeIds[$node->parent_node_id]))
                        {
                            // if we're in the search node list, the user selected the node explicitly
                            // if the parent is in the list, then that node was selected via traversal so we're included too
                            $searchNodeIds[$id] = true;
                        }
                        // we still need to traverse children though, as children may be selected
                    });

                    $nodeIds = array_unique(array_keys($searchNodeIds));
                }
                else
                {
                    Arr::unsetUrlConstraint($urlConstraints, 'c.child_nodes');
                }

                $query->withMetadata('node', $nodeIds);
                Arr::setUrlConstraint($urlConstraints, 'c.nodes', $nodeIds);
            }
            else
            {
                Arr::unsetUrlConstraint($urlConstraints, 'c.nodes');
                Arr::unsetUrlConstraint($urlConstraints, 'c.child_nodes');
            }
        }
    }
}