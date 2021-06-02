<?php

namespace SV\ReportImprovements\XF\Entity;

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
class ThreadReplyBan extends XFCP_ThreadReplyBan
{
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

    protected function _postSave()
    {
        parent::_postSave();

        if ($this->getOption('svLogWarningChanges'))
        {
            $type = null;
            if ($this->isInsert())
            {
                $type = 'new';
            }
            else if ($this->isUpdate() && $this->hasChanges())
            {
                $type = 'edit';
                if (\XF::$time >= $this->expiry_date)
                {
                    $type = 'expire';
                }
            }

            if ($type)
            {
                /** @var \SV\ReportImprovements\XF\Repository\ThreadReplyBan $threadReplyBanRepo */
                $threadReplyBanRepo = $this->repository('XF:ThreadReplyBan');
                $threadReplyBanRepo->logToReport($this, $type,
                    (bool)$this->getOption('svResolveReport'),
                    (bool)$this->getOption('svResolveReportAlert'),
                    $this->getOption('svResolveReportAlertComment')
                );
            }
        }
    }

    protected function _postDelete()
    {
        parent::_postDelete();

        if (Globals::$resolveReplyBanOnDelete)
        {
            // TODO: fix me; racy
            $report = $this->Report;

            $resolveWarningReport = !$report || $report->canView() && $report->canUpdate($error);
            $this->setOption('svResolveReport', $resolveWarningReport);
        }

        if ($this->getOption('svLogWarningChanges'))
        {
            $type = 'delete';
            if (\XF::$time >= $this->expiry_date)
            {
                $type = 'expire';
            }

            /** @var \SV\ReportImprovements\XF\Repository\ThreadReplyBan $threadReplyBanRepo */
            $threadReplyBanRepo = $this->repository('XF:ThreadReplyBan');
            $threadReplyBanRepo->logToReport($this, $type,
                (bool)$this->getOption('svResolveReport'),
                (bool)$this->getOption('svResolveReportAlert'),
                $this->getOption('svResolveReportAlertComment')
            );
        }
    }

    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns['post_id'] = ['type' => self::UINT, 'default' => null, 'nullable' => true];

        $structure->relations['Report'] = [
            'entity'     => 'XF:Report',
            'type'       => self::TO_ONE,
            'conditions' => [
                ['content_type', '=', 'user'],
                ['content_id', '=', '$user_id'],
            ],
        ];
        $structure->relations['Post'] = [
            'entity'     => 'XF:Post',
            'type'       => self::TO_ONE,
            'conditions' => 'post_id',
            'primary'    => true,
        ];

        $structure->getters['Report'] = true;
        $structure->options['svLogWarningChanges'] = true;
        $structure->options['svResolveReport'] = false;
        $structure->options['svResolveReportAlert'] = false;
        $structure->options['svResolveReportAlertComment'] = '';

        return $structure;
    }
}