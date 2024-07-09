<?php

namespace SV\ReportImprovements\XF\Repository\XF22;

use SV\ReportImprovements\XF\Repository\XFCP_ReportPatch;
use XF\Mvc\Entity\ArrayCollection;

class ReportPatch extends XFCP_ReportPatch
{
    /**
     * @param \XF\Entity\Report $report
     * @return ArrayCollection
     * @throws \Exception
     * @noinspection PhpMissingParentCallCommonInspection
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function getModeratorsWhoCanHandleReport(\XF\Entity\Report $report)
    {
        /** @var \SV\ReportImprovements\XF\Repository\Report $this */
        return $this->svGetModeratorsWhoCanHandleReport($report);
    }
}