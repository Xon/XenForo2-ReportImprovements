<?php

namespace SV\ReportImprovements\XF\Entity;

use SV\ReportImprovements\Entity\IReportResolver;
use SV\ReportImprovements\Entity\ReportResolverTrait;
use SV\ReportImprovements\Globals;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * Class ThreadReplyBan
 * @extends \XF\Entity\ThreadReplyBan
 *
 * @package SV\ReportImprovements\XF\Entity
 * COLUMNS
 * @property int|null         $post_id
 * GETTERS
 * @property-read Report|null $Report
 * RELATIONS
 * @property-read Post|null   $Post
 */
class ThreadReplyBan extends XFCP_ThreadReplyBan implements IReportResolver
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

        if ($this->post_id !== null)
        {
            return $this->finder('XF:Report')
                        ->where('content_type', 'post')
                        ->where('content_id', $this->post_id)
                        ->fetchOne();
        }

        return $this->finder('XF:Report')
                    ->where('content_type', 'user')
                    ->where('content_id', $this->user_id)
                    ->fetchOne();
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