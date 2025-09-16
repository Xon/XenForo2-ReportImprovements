<?php
/**
 * @noinspection PhpMultipleClassDeclarationsInspection
 */

namespace SV\ReportImprovements\XF\Entity;


use SV\ReportImprovements\Enums\ReportType;
use SV\ReportImprovements\Enums\WarningType;
use SV\ReportImprovements\XF\Repository\Report as ExtendedReportRepo;
use SV\StandardLib\Helper;
use XF\Phrase;
use XF\Repository\Report as ReportRepo;
use function assert;
use function is_array;

/**
 * @extends \XF\Entity\Search
 */
class Search extends XFCP_Search
{
    protected function setupConstraintFields(): void
    {
        parent::setupConstraintFields();

        $this->svDateConstraint[] = 'warning_expiry_lower';
        $this->svDateConstraint[] = 'warning_expiry_upper';
        $this->svUserConstraint[] = 'report_user';
        $this->svUserConstraint[] = 'warning_mod';
        $this->svIgnoreConstraint[] = 'child_categories';
    }

    protected function expandStructuredSearchConstraint(array &$query, string $key, $value): bool
    {
        // todo simplify this
        if ($key === 'report_state' && is_array($value))
        {
            /** @var ExtendedReportRepo $reportRepo */
            $reportRepo = Helper::repository(ReportRepo::class);
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
            /** @var ExtendedReportRepo $reportRepo */
            $reportRepo = Helper::repository(ReportRepo::class);
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
        else if ($key === 'warning_definition' && is_array($value))
        {
            /** @var ExtendedReportRepo $reportRepo */
            $reportRepo = Helper::repository(ReportRepo::class);
            $states = $reportRepo->getWarningDefinitionsForSearch();

            foreach ($value as $subKey => $id)
            {
                $id = (int)$id;
                $query[$key . '_' . $subKey] = \XF::phrase('svSearchConstraint.warning_definition', [
                    'value' => $states[$id] ?? $id,
                ]);
            }

            return true;
        }

        return parent::expandStructuredSearchConstraint($query, $key, $value);
    }

    protected function formatConstraintValue(string $key, $value)
    {
        if ($key === 'content_deleted' || $key === 'content_warned')
        {
            if ($value === '')
            {
                $value = 'include';
            }

            return \XF::phrase('svReportImprove_content.'.$value);
        }

        return parent::formatConstraintValue($key, $value);
    }

    protected function getSpecializedSearchConstraintPhrase(string $key, $value): ?Phrase
    {
        if ($key === 'warning_expiry_type')
        {
            if ($value === 'date')
            {
                return null;
            }

            return \XF::phrase('svSearchConstraint.warning_'.$value);
        }

        return parent::getSpecializedSearchConstraintPhrase($key, $value);
    }
}