<?php

namespace SV\ReportImprovements\SV\ForumBan\Entity;

use SV\ReportImprovements\Entity\IReportResolver;
use SV\ReportImprovements\Entity\ReportResolverTrait;
use SV\ReportImprovements\Globals;
use SV\ReportImprovements\XF\Entity\Report;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * Class ForumBan
 * @extends \SV\ForumBan\Entity\ForumBan
 *
 * @package SV\ReportImprovements\SV\ForumBan\Entity
 *
 * GETTERS
 * @property-read Report|null $Report
 */
class ForumBan extends XFCP_ForumBan implements IReportResolver
{
    use ReportResolverTrait;

    /**
     * @return Report|Entity|null
     */
    protected function getReport()
    {
        if (\array_key_exists('Report', $this->_relations))
        {
            return $this->_relations['Report'];
        }

        return $this->finder('XF:Report')
                    ->where('content_type', 'user')
                    ->where('content_id', $this->user_id)
                    ->fetchOne();
    }

    protected function _postDelete(): void
    {
        parent::_postDelete();

        if (Globals::$resolveReplyBanOnDelete)
        {
            $resolveWarningReport = $this->canResolveLinkedReport();
            $this->setOption('svResolveReport', $resolveWarningReport);
            // triggers action in ReportResolver::_postDelete()
        }
    }

    /**
     * @return \XF\Entity\User|null
     */
    public function getResolveUser(): ?\XF\Entity\User
    {
        $reporter = null;
        if (!$this->User)
        {
            $reporter = $this->User;
        }
        if (!$reporter && $this->BannedBy)
        {
            $reporter = $this->BannedBy;
        }
        return $reporter;
    }

    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure): Structure
    {
        $structure = parent::getStructure($structure);

        $structure->getters['Report'] = ['getter' => 'getReport', 'cache' => true];
        $structure->options['svLogWarningChanges'] = true;

        static::addReportResolverStructureElements($structure, [
            ['content_type', '=', 'user'],
            ['content_id', '=', '$user_id'],
        ], [
            'isExpiredField' => null,
        ]);

        return $structure;
    }
}