<?php

namespace SV\ReportImprovements\XF\Entity;

use SV\ReportImprovements\Behavior\ReportResolver;
use SV\ReportImprovements\Entity\IReportResolver;
use SV\ReportImprovements\Entity\ReportResolverTrait;
use SV\ReportImprovements\Entity\WarningInfoTrait;
use SV\ReportImprovements\SV\ForumBan\Entity\ForumBan as ExtendedForumBanEntity;
use XF\Entity\User as UserEntity;
use XF\Mvc\Entity\Structure;
use function strlen;

/**
 * @extends \XF\Entity\Warning
 *
 * GETTERS
 * @property-read ?string $definition_title
 * RELATIONS
 * @property-read ?Report $Report
 */
class Warning extends XFCP_Warning implements IReportResolver
{
    use ReportResolverTrait;
    use WarningInfoTrait;

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

    /** @var array<ExtendedForumBanEntity> */
    protected $svForumBans = [];


    public function setSvForumBans(array $svForumBans)
    {
        $this->svForumBans = $svForumBans;
    }

    /**
     * @throws \Exception
     */
    protected function _postSave()
    {
        $report = null;
        if ($this->svReplyBan)
        {
            $this->svReplyBan->saveIfChanged();
            $report = $this->svReplyBan->Report;
            $this->hydrateRelation('Report', $report);
        }

        foreach ($this->svForumBans as $forumBan)
        {
            if ($report !== null)
            {
                $forumBan->hydrateRelation('Report', $report);
            }
            $forumBan->saveIfChanged();

            if ($report === null)
            {
                $report = $forumBan->Report;
                $this->hydrateRelation('Report', $report);
            }
        }

        $report = $this->Report;
        if ($this->isInsert() && $report !== null)
        {
            $report->triggerReindex(true);
        }

        parent::_postSave();
    }

    protected function _postDelete()
    {
        parent::_postDelete();

        $report = $this->Report;
        if ($report !== null)
        {
            $report->triggerReindex(true);
        }
    }

    /**
     * @return UserEntity|null
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

    protected function verifyTitle(string &$title): bool
    {
        // prevent silent truncation of the warning title
        // This prevents errors when attempting to copy the title to WarningLog
        $maxLength = (int)($this->_structure->columns['title']['maxLength'] ?? 0);
        if ($maxLength > 0 && strlen($title) > $maxLength)
        {
            $this->error(\XF::phrase('sv_please_enter_warning_title_using_x_characters_or_fewer', ['count' => $maxLength]),'title');
            return false;
        }

        return true;
    }

    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->getters['definition_title'] = ['getter' => 'getDefinitionTitle', 'cache' => true];

        static::addReportResolverStructureElements($structure, [
            ['content_type', '=', '$content_type'],
            ['content_id', '=', '$content_id'],
        ], [
        ]);

        return $structure;
    }
}