<?php

namespace SV\ReportImprovements\XF\Entity;

class ApprovalQueuePatch extends XFCP_ApprovalQueuePatch
{
    public function getReport()
    {
        // workaround SV/ReportCentreEssentials defined a crappy version of getReport
        /** @noinspection PhpUndefinedMethodInspection */
        return $this->getSvReport();
    }
}