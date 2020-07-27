<?php

namespace SV\ReportImprovements\Reaction;

use SV\ReportImprovements\XF\Entity\ReportComment as ExtendedReportCommentEntity;
use XF\Mvc\Entity\Entity;
use XF\Reaction\AbstractHandler;

/**
 * Class ReportComment
 *
 * @package SV\ReportImprovements\Reaction
 */
class ReportComment extends AbstractHandler
{
    /**
     * @param Entity|ExtendedReportCommentEntity $entity
     * @param null $error
     *
     * @return mixed
     */
    public function canViewContent(Entity $entity, &$error = null)
    {
        return parent::canViewContent($entity, $error);
    }

    /**
     * @param Entity|ExtendedReportCommentEntity $entity
     *
     * @return bool
     */
    public function reactionsCounted(Entity $entity)
    {
        return true;
    }

    protected function getExtraDataForAlertOrFeed(Entity $content, $context)
    {
        if ($context !== 'alert')
        {
            return [];
        }

        return [
            'depends_on_addon_id' => 'SV/ReportImprovements',
        ];
    }

    /**
     * @return array
     */
    public function getEntityWith()
    {
        return ['Report'];
    }
}