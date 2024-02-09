<?php

namespace SV\ReportImprovements\ApprovalQueue;

use XF\Mvc\Entity\Entity;

interface IContainerToContent
{
    public function getContainerToContentJoins(): array;
    public function getReportableContent(Entity $content): ?Entity;
}