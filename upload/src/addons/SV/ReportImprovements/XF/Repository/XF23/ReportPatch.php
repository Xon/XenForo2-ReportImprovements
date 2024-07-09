<?php

namespace SV\ReportImprovements\XF\Repository\XF23;

use SV\ReportImprovements\XF\Repository\Report as ExtendedReportRepo;
use SV\ReportImprovements\XF\Repository\XFCP_ReportPatch;
use XF\Entity\Report as ReportEntity;
use XF\Mvc\Entity\ArrayCollection;

class ReportPatch extends XFCP_ReportPatch
{
    /**
     * @param ReportEntity $report
     * @param false        $notifiableOnly
     * @return ArrayCollection
     * @noinspection PhpMissingParentCallCommonInspection
     * @noinspection PhpMissingReturnTypeInspection
     * @noinspection PhpSignatureMismatchDuringInheritanceInspection
     * @noinspection RedundantSuppression
     */
    public function getModeratorsWhoCanHandleReport(ReportEntity $report, $notifiableOnly = false)
    {
        /** @var ExtendedReportRepo $this */
        return $this->svGetModeratorsWhoCanHandleReport($report, $notifiableOnly);
    }
}