<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace SV\ReportImprovements\XF\Entity;

use SV\ReportImprovements\ApprovalQueue\IContainerToContent;
use XF\Entity\Report as ReportEntity;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Repository\Report as ReportRepo;
use function array_key_exists;
use function is_callable;

/**
 * Class ApprovalQueue
 * @extends \XF\Entity\ApprovalQueue
 *
 * @package SV\ReportImprovements\XF\Entity
 * @property-read ?Report $Report
 * @property-read ?Report $Report_
 * @property-read ?Entity $ReportableContent
 */
class ApprovalQueue extends XFCP_ApprovalQueue
{
    public function canReport(&$error = null): bool
    {
        $reportableContent = $this->ReportableContent;
        if ($reportableContent === null)
        {
            return false;
        }

        if (is_callable([$reportableContent, 'canReportFromApprovalQueue']))
        {
            $canReport = $reportableContent->canReportFromApprovalQueue($error);
        }
        else if (is_callable([$reportableContent, 'canReport']))
        {
            $canReport = $reportableContent->canReport($error);
        }
        else
        {
            $canReport = \XF::visitor()->canReport($error);
        }

        if (!$canReport)
        {
            return false;
        }

        if ($this->Report !== null)
        {
            return false;
        }

        return true;
    }

    protected function getReportableContent(): ?Entity
    {
        $content = $this->Content;
        if ($content === null)
        {
            return null;
        }

        return $this->getReportableContentInternal($content);
    }

    protected function getReportableContentInternal(Entity $content, int $recursionLimit = 10): ?Entity
    {
        $contentType = $content->getEntityContentType();

        /** @var ReportRepo $reportRepo */
        $reportRepo = $this->repository('XF:Report');
        if ($reportRepo->getReportHandler($contentType, false))
        {
            // has a report handler so get return the content type and content id
            return $content;
        }

        $handler = $this->getHandler();
        if ($handler instanceof IContainerToContent)
        {
            return $handler->getReportableContent($content);
        }

        // nested Content thingy?
        if (!$content->isValidKey('Content'))
        {
            return null;
        }

        $nestedContent = $content->get('Content');
        if ($recursionLimit > 0 && $nestedContent instanceof Entity)
        {
            return $this->getReportableContentInternal($nestedContent, $recursionLimit - 1);
        }

        return null;
    }

    protected function getSvReport(): ?ReportEntity
    {
        if (array_key_exists('Report', $this->_relations))
        {
            return $this->_relations['Report'];
        }

        $content = $this->ReportableContent;
        if ($content === null)
        {
            return null;
        }

        if ($content->isValidKey('Report'))
        {
            $report = $content->get('Report');
            if ($report instanceof ReportEntity)
            {
                $this->hydrateRelation('Report', $report);
                return $report;
            }

            $this->hydrateRelation('Report', null);
            return null;
        }

        /** @var \XF\Finder\Report $reportFinder */
        $reportFinder = $this->finder('XF:Report');
        /** @var ReportEntity $report */
        $report = $reportFinder
            ->where('content_type', $content->getEntityContentType())
            ->where('content_id', $content->getEntityId())
            ->fetchOne();

        $this->hydrateRelation('Report', $report);

        return $report;
    }

    /**
     * @param Entity|null $content
     */
    public function setContent(Entity $content = null)
    {
        parent::setContent($content);

        unset($this->_relations['Report']);
        $report = $this->Report;
        if ($report !== null && $content !== null)
        {
            $report->setContent($this->ReportableContent);
        }
    }

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
                ['content_type', '=', '$content_type'],
                ['content_id', '=', '$content_id'],
            ],
            'primary' => true,
        ];

        $structure->relations['User'] = [
            'entity'     => 'XF:User',
            'type'       => self::TO_ONE,
            'conditions' => [
                ['$content_type', '=', 'user'],
                ['user_id', '=', '$content_id'],
            ],
        ];

        $structure->getters['Report'] = ['getter' => 'getSvReport', 'cache' => true];
        $structure->getters['ReportableContent'] = ['getter' => 'getReportableContent', 'cache' => true];

        return $structure;
    }
}