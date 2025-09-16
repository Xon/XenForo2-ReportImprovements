<?php

namespace SV\ReportImprovements\Reaction;

use SV\ReportImprovements\XF\Entity\ReportComment as ExtendedReportCommentEntity;
use SV\ReportImprovements\XF\Repository\Report as ExtendedReportRepo;
use SV\StandardLib\Helper;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Phrase;
use XF\Reaction\AbstractHandler;
use XF\Repository\Report as ReportRepo;

class ReportComment extends AbstractHandler
{
    /**
     * @param Entity|ExtendedReportCommentEntity $entity
     * @param Phrase|String|null $error
     * @return bool
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

    public function getContent($id)
    {
        $entities = parent::getContent($id);

        if ($entities instanceof AbstractCollection)
        {
            /** @var ExtendedReportRepo $reportRepo */
            $reportRepo = Helper::repository(ReportRepo::class);
            $reportRepo->svPreloadReportComments($entities);
        }

        return $entities;
    }

    /**
     * @return array
     */
    public function getEntityWith()
    {
        return ['Report'];
    }
}