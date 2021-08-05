<?php

namespace SV\ReportImprovements\XF\Entity;

use SV\ReportImprovements\Entity\IReportResolver;
use SV\ReportImprovements\Entity\ReportResolverTrait;
use SV\ReportImprovements\Globals;
use XF\Mvc\Entity\Structure;

/**
 * Class ThreadReplyBan
 * Extends \XF\Entity\ThreadReplyBan
 *
 * @package SV\ReportImprovements\XF\Entity
 * COLUMNS
 * @property int    post_id
 * GETTERS
 * @property Report Report
 * RELATIONS
 * @property Post   Post
 */
class ThreadReplyBan extends XFCP_ThreadReplyBan implements IReportResolver
{
    use ReportResolverTrait;

    /**
     * @return Report|\XF\Mvc\Entity\Entity|null
     */
    protected function getReport()
    {
        $report = null;
        if ($this->post_id)
        {
            $report = $this->finder('XF:Report')
                           ->where('content_type', 'post')
                           ->where('content_id', $this->post_id)
                           ->fetchOne();
        }
        if (!$report)
        {
            $report = $this->finder('XF:Report')
                           ->where('content_type', 'user')
                           ->where('content_id', $this->user_id)
                           ->fetchOne();
        }

        return $report;
    }

    protected function _postDelete()
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
    public function getResolveUser()
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
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns['post_id'] = ['type' => self::UINT, 'default' => null, 'nullable' => true];

        $structure->relations['Post'] = [
            'entity'     => 'XF:Post',
            'type'       => self::TO_ONE,
            'conditions' => 'post_id',
            'primary'    => true,
        ];

        $structure->getters['Report'] = true;
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