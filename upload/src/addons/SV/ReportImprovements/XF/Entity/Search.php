<?php
/**
 * @noinspection PhpMultipleClassDeclarationsInspection
 */

namespace SV\ReportImprovements\XF\Entity;

use SV\ReportImprovements\Entity\WarningLog as WarningLogEntity;
use SV\ReportImprovements\XF\Repository\Report as ReportRepo;
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

        $this->svDateConstraint[] = 'warning_expiry_lower';
        $this->svDateConstraint[] = 'warning_expiry_upper';
        $this->svUserConstraint[] = 'warning_user';
        $this->svUserConstraint[] = 'participants';
        $this->svIgnoreConstraint[] = 'child_categories';
    }

    protected function expandStructuredSearchConstraint(array &$query, string $key, $value): bool
    {
        if ($key === 'report_state' && is_array($value))
        {
            $reportRepo = \XF::repository('XF:Report');
            assert($reportRepo instanceof ReportRepo);
            $states = $reportRepo->getReportStatePairs();

            foreach ($value as $subKey => $id)
            {
                $id = (string)$id;
                $query[$key . '_' . $subKey] = \XF::phrase('svSearchConstraint.report_state', [
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

            foreach ($value as $subKey => $id)
            {
                $id = (string)$id;
                $query[$key . '_' . $subKey] = \XF::phrase('svSearchConstraint.report_type', [
                    'value' => $states[$id]['phrases'] ?? $id,
                ]);
            }

            return true;
        }
        else if ($key === 'warning_type' && is_array($value))
        {
            $states = WarningLogEntity::getWarningTypesPairs();

            foreach ($value as $subKey => $id)
            {
                $id = (string)$id;
                $query[$key . '_' . $subKey] = \XF::phrase('svSearchConstraint.warning_type', [
                    'type' => $states[$id] ?? $id,
                ]);
            }

            return true;
        }
        else if ($key === 'categories' && is_array($value))
        {
            // This can be a ticket category or XFRM/etc :(
            // for now assume tickets
            foreach ($value as $subKey => $id)
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
                    $query[$key . '_' . $subKey] = \XF::phrase('svSearchConstraint.nodes', [
                        'url' => $category->getContentUrl(),
                        'node' => $category->getContentTitle(),
                    ]);
                }
            }
        }

        return parent::expandStructuredSearchConstraint($query, $key, $value);
    }
}