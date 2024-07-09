<?php

namespace SV\ReportImprovements\XF\Repository\XF23;

use SV\ReportImprovements\XF\Repository\XFCP_ReportPatch;
use XF\Mvc\Entity\ArrayCollection;

class ReportPatch extends XFCP_ReportPatch
{
    /**
     * @param \XF\Entity\Report $report
     * @param false             $notifiableOnly
     * @return ArrayCollection
     * @throws \Exception
     * @noinspection PhpMissingParentCallCommonInspection
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function getModeratorsWhoCanHandleReport(\XF\Entity\Report $report, $notifiableOnly = false)
    {
        /** @var \SV\ReportImprovements\XF\Repository\Report $this */
        return $this->svGetModeratorsWhoCanHandleReport($report, $notifiableOnly);
    }
}