<?php

namespace SV\ReportImprovements\XF\Entity;

use SV\ReportImprovements\Behavior\ReportResolver;
use SV\ReportImprovements\Entity\IReportResolver;
use SV\ReportImprovements\Entity\ReportResolverTrait;
use XF\Mvc\Entity\Structure;

/**
 * Class Warning
 * Extends \XF\Entity\Warning
 *
 * @package SV\ReportImprovements\XF\Entity
 * RELATIONS
 * @property Report Report
 */
class Warning extends XFCP_Warning implements IReportResolver
{
    use ReportResolverTrait;

    public function getSvLogOperationTypeForReportResolve(): string
    {
        return (string)$this->getSvLogOperationType();
    }

    /**
     * Support for Warning Acknowledgments, do not change type signature!
     *
     * @return string|null
     */
    protected function getSvLogOperationType()
    {
        /** @var ReportResolver $behavior */
        $behavior = $this->getBehavior('SV\ReportImprovements:ReportResolver');
        return $behavior->getLogOperationType();
    }

    /** @var ThreadReplyBan */
    protected $svReplyBan = null;

    /**
     * @param ThreadReplyBan $svReplyBan
     */
    public function setSvReplyBan(ThreadReplyBan $svReplyBan)
    {
        $this->svReplyBan = $svReplyBan;
    }

    /**
     * @throws \Exception
     */
    protected function _postSave()
    {
        if ($this->svReplyBan)
        {
            $this->svReplyBan->saveIfChanged();
            $this->hydrateRelation('Report', $this->svReplyBan->Report);
        }

        parent::_postSave();
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
        if (!$reporter && $this->WarnedBy)
        {
            $reporter = $this->WarnedBy;
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

        static::addReportResolverStructureElements($structure, [
            ['content_type', '=', '$content_type'],
            ['content_id', '=', '$content_id'],
        ], [
        ]);

        return $structure;
    }
}