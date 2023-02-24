<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace SV\ReportImprovements\XF\Entity;

use XF\Mvc\Entity\Structure;

/**
 * Class ApprovalQueue
 * Extends \XF\Entity\ApprovalQueue
 *
 * @package SV\ReportImprovements\XF\Entity
 * @property-read Report $Report
 */
class ApprovalQueue extends XFCP_ApprovalQueue
{
    public function canReport(&$error = null)
    {
        /** @var \XF\Repository\Report $reportRepo */
        $reportRepo = $this->repository('XF:Report');
        if (!$reportRepo->getReportHandler($this->content_type))
        {
            return false;
        }

        if (\is_callable([$this->Content, 'canReportFromApprovalQueue']))
        {
            $canReport = $this->Content->canReportFromApprovalQueue($error);
        }
        else if (\is_callable([$this->Content, 'canReport']))
        {
            $canReport = $this->Content->canReport($error);
        }
        else
        {
            $canReport = \XF::visitor()->canReport($error);
        }

        if (!$canReport)
        {
            return false;
        }

        if ($this->Report)
        {
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

        $structure->relations['Report'] = [
            'entity'     => 'XF:Report',
            'type'       => self::TO_ONE,
            'conditions' => [
                ['content_type', '=', '$content_type'],
                ['content_id', '=', '$content_id'],
            ],
        ];

        return $structure;
    }
}