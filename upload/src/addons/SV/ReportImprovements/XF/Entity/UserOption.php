<?php

namespace SV\ReportImprovements\XF\Entity;

use XF\Mvc\Entity\Structure;

/**
 * @property array|null $sv_reportimprov_approval_filters
 */
class UserOption extends XFCP_UserOption
{
    /** @noinspection PhpMissingReturnTypeInspection */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns['sv_reportimprov_approval_filters'] = ['type' => self::JSON_ARRAY, 'default' => null, 'nullable' => true];

        return $structure;
    }
}