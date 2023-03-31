<?php
/**
 * @noinspection PhpMultipleClassDeclarationsInspection
 */

namespace SV\ReportImprovements\XF\Entity;

use SV\ReportImprovements\Enums\ReportType;
use SV\ReportImprovements\Enums\WarningType;
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
        $this->svIgnoreConstraint[] = 'child_categories';
    }

    protected function expandStructuredSearchConstraint(array &$query, string $key, $value): bool
    {
        // todo simplify this
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
            $states = ReportType::getPairs();

            foreach ($value as $subKey => $id)
            {
                $id = (string)$id;
                $query[$key . '_' . $subKey] = \XF::phrase('svSearchConstraint.report_type', [
                    'value' => $states[$id] ?? $id,
                ]);
            }

            return true;
        }
        else if ($key === 'report_content' && is_array($value))
        {
            $reportRepo = \XF::repository('XF:Report');
            assert($reportRepo instanceof ReportRepo);
            $states = $reportRepo->getReportContentTypePhrasePairs(true);

            foreach ($value as $subKey => $id)
            {
                $id = (string)$id;
                $query[$key . '_' . $subKey] = \XF::phrase('svSearchConstraint.report_content_type', [
                    'value' => $states[$id] ?? $id,
                ]);
            }

            return true;
        }
        else if ($key === 'warning_type' && is_array($value))
        {
            $states = WarningType::getPairs();

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
            /** @var \NF\Tickets\Repository\Category $categoryRepo */
            $categoryRepo = \XF::repository('NF\Tickets:Category');
            $categories = $categoryRepo->getViewableCategories();

            // This can be a ticket category or XFRM/etc :(
            // for now assume tickets
            foreach ($value as $subKey => $id)
            {
                $id = (int)$id;
                if ($id === 0)
                {
                    continue;
                }

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

    protected function getSpecializedSearchConstraintPhrase(string $key, $value): ?\XF\Phrase
    {
        if ($key === 'warning_expired')
        {
            switch ($value)
            {
                case 'expired':
                    return \XF::phrase('svSearchConstraint.warning_expired');
                case 'active':
                    return \XF::phrase('svSearchConstraint.warning_active');
                case 'date':
                    return null;
            }
        }

        return parent::getSpecializedSearchConstraintPhrase($key, $value);
    }
}