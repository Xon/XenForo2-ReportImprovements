<?php
/**
 * @noinspection PhpMultipleClassDeclarationsInspection
 */

namespace SV\ReportImprovements\XF\Entity;

use SV\ReportImprovements\XF\Repository\Report as ReportRepo;
use SV\Threadmarks\Repository\ThreadmarkCategory as ThreadmarkCategoryRepo;
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

        return parent::expandStructuredSearchConstraint($query, $key, $value);
    }
}