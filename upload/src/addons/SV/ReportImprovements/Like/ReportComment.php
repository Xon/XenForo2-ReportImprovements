<?php

namespace SV\ReportImprovements\Like;

use XF\Like\AbstractHandler;
use XF\Mvc\Entity\Entity;

/**
 * Class ReportComment
 *
 * @package SV\ReportImprovements\Like
 */
class ReportComment extends AbstractHandler
{
    /**
     * @param Entity|\SV\ReportImprovements\XF\Entity\ReportComment $entity
     *
     * @return bool
     */
    public function likesCounted(Entity $entity)
    {
        return true;
    }
}