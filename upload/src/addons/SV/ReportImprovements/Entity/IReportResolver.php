<?php

namespace SV\ReportImprovements\Entity;

use SV\ReportImprovements\XF\Entity\Report as ExtendedReportEntity;
use XF\Entity\User;

/**
 * @property-read ExtendedReportEntity|null $Report
 */
interface IReportResolver
{
    public function canResolveLinkedReport(): bool;

    /**
     * @return User|null
     */
    public function getResolveUser();

    /**
     * @param bool   $resolveReport
     * @param bool   $alert
     * @param string $alertComment
     * @return ExtendedReportEntity|null
     */
    public function resolveReportFor(bool $resolveReport, bool $alert, string $alertComment);
}