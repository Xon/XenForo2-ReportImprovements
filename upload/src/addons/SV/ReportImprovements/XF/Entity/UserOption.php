<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace SV\ReportImprovements\XF\Entity;

use XF\Mvc\Entity\Structure;

/**
 * Class UserOption
 *
 * @package SV\ReportImprovements\XF\Entity
 */
class UserOption extends XFCP_UserOption
{
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns['sv_reportimprov_approval_filters'] = ['type' => self::JSON_ARRAY, 'default' => null, 'nullable' => true];

        return $structure;
    }
}