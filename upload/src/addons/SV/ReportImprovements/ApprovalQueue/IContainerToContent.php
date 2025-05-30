<?php

namespace SV\ReportImprovements\ApprovalQueue;

use XF\Mvc\Entity\Entity;

/**
 * @template T of Entity
 */
interface IContainerToContent
{
    public function getContainerToContentJoins(): array;

    /**
     * @param T $content
     * @return Entity|null
     */
    public function getReportableContent(Entity $content): ?Entity;
}