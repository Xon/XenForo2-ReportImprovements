<?php

namespace SV\ReportImprovements\XF\Entity;

use XF\Mvc\Entity\Structure;

/**
 * Class Post
 * @extends \XF\Entity\Post
 *
 * @package SV\ReportImprovements\XF\Entity
 * @property-read Report $Report
 */
class Post extends XFCP_Post
{
    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->relations['Report'] = [
            'entity'     => 'XF:Report',
            'type'       => self::TO_ONE,
            'conditions' => [
                ['content_type', '=', 'post'],
                ['content_id', '=', '$post_id'],
            ],
        ];

        return $structure;
    }
}