<?php

namespace SV\ReportImprovements\SV\ForumBan\Entity;

use SV\ReportImprovements\Entity\IReportResolver;
use SV\ReportImprovements\Entity\ReportResolverTrait;
use SV\ReportImprovements\Globals;
use SV\ReportImprovements\XF\Entity\Post as ExtendedPostEntity;
use SV\ReportImprovements\XF\Entity\Report as ExtendedReportEntity;
use SV\StandardLib\Helper;
use XF\Entity\User as UserEntity;
use XF\Finder\Report as ReportFinder;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * Class ForumBan
 *
 * @extends \SV\ForumBan\Entity\ForumBan
 * COLUMNS
 * @property int|null                       $post_id
 * GETTERS
 * @property-read ExtendedReportEntity|null $Report
 * RELATIONS
 * @property-read ExtendedPostEntity|null   $Post
 */
class ForumBan extends XFCP_ForumBan implements IReportResolver
{
    use ReportResolverTrait;

    /**
     * @return ExtendedReportEntity|Entity|null
     */
    protected function getReport()
    {
        if (\array_key_exists('Report', $this->_relations))
        {
            return $this->_relations['Report'];
        }

        if ($this->post_id !== null)
        {
            return Helper::finder(ReportFinder::class)
                         ->where('content_type', 'post')
                         ->where('content_id', $this->post_id)
                         ->fetchOne();
        }

        return Helper::finder(ReportFinder::class)
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

    public function getResolveUser(): ?UserEntity
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