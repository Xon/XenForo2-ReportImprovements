<?php

namespace SV\ReportImprovements\XF\Pub\Controller;

/**
 * Class Warning
 * 
 * Extends \XF\Pub\Controller\Warning
 *
 * @package SV\ReportImprovements\XF\Pub\Controller
 */
class Warning extends XFCP_Warning
{
    /**
     * @param int $id
     * @param array $extraWith
     *
     * @return \XF\Entity\Warning
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertViewableWarning($id, array $extraWith = [])
    {
        $extraWith[] = 'Report';
        return parent::assertViewableWarning($id, $extraWith);
    }
}