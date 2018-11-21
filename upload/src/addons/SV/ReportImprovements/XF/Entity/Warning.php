<?php

namespace SV\ReportImprovements\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * Class Warning
 * 
 * Extends \XF\Entity\Warning
 *
 * @package SV\ReportImprovements\XF\Entity
 *
 * RELATIONS
 * @property \SV\ReportImprovements\XF\Entity\Report Report
 */
class Warning extends XFCP_Warning
{
    /**
     * @param Structure $structure
     *
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->relations['Report'] = [
            'entity' => 'XF:Report',
            'type' => self::TO_ONE,
            'conditions' => [
                ['content_type', '=', '$content_type'],
                ['content_id', '=', '$content_id']
            ]
        ];
    
        return $structure;
    }
}