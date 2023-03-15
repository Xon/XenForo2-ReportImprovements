<?php
/**
 * @noinspection PhpMultipleClassDeclarationsInspection
 */

namespace SV\ReportImprovements\XF\Entity;

use SV\ReportImprovements\XF\Repository\Report as ReportRepo;
use SV\Threadmarks\Repository\ThreadmarkCategory as ThreadmarkCategoryRepo;
use XF\Entity\LinkableInterface;
use function assert;
use function is_array;

/**
 * Extends \XF\Entity\Search
 */
class Search extends XFCP_Search
{
    protected function setupConstraintFields(): void
    {
        parent::setupConstraintFields();

        $this->svUserConstraint[] = 'warning_user';
        $this->svIgnoreConstraint[] = 'report_state';
        $this->svIgnoreConstraint[] = 'report_type';
        $this->svIgnoreConstraint[] = 'child_categories';
        $this->svIgnoreConstraint[] = 'categories';
    }

    protected function expandStructuredSearchConstraint(array &$query, string $key, $value): bool
    {
        if ($key === 'report_state' && is_array($value))
        {
            $reportRepo = \XF::repository('XF:Report');
            assert($reportRepo instanceof ReportRepo);
            $states = $reportRepo->getReportStatePairs();

            foreach ($value as $id)
            {
                $id = (string)$id;
                $query[$key . '_' . $id] = \XF::phrase('svSearchConstraint.report_state', [
                    'value' => $states[$id] ?? $id,
                ]);
            }

            return true;
        }
        else if ($key === 'report_type' && is_array($value))
        {
            $reportRepo = \XF::repository('XF:Report');
            assert($reportRepo instanceof ReportRepo);
            $states = $reportRepo->getReportTypes();

            foreach ($value as $id)
            {
                $id = (string)$id;
                $query[$key . '_' . $id] = \XF::phrase('svSearchConstraint.report_type', [
                    'value' => $states[$id]['phrases'] ?? $id,
                ]);
            }

            return true;
        }
        else if ($key === 'categories' && is_array($value))
        {
            // This can be a ticket category or XFRM/etc :(
            // for now assume tickets
            foreach ($value as $id)
            {
                $id = (int)$id;
                if ($id === 0)
                {
                    continue;
                }

                /** @var \NF\Tickets\Repository\Category $categoryRepo */
                $categoryRepo = \XF::repository('NF\Tickets:Category');
                $categories = $categoryRepo->getViewableCategories();

                /** @var \NF\Tickets\Entity\Category|null $category */
                $category = $categories[$id] ?? null;
                if ($category instanceof LinkableInterface)
                {
                    $query[$key . '_' . $id] = \XF::phrase('svSearchConstraint.nodes', [
                        'url' => $category->getContentUrl(),
                        'node' => $category->getContentTitle(),
                    ]);
                }
            }
        }

        return parent::expandStructuredSearchConstraint($query, $key, $value);
    }
}