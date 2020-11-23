<?php

namespace SV\ReportImprovements\XF\Entity;

use SV\ReportImprovements\Globals;
use XF\Mvc\Entity\Structure;

/**
 * Class Warning
 * Extends \XF\Entity\Warning
 *
 * @package SV\ReportImprovements\XF\Entity
 * RELATIONS
 * @property Report Report
 */
class Warning extends XFCP_Warning
{
    /**
     * @return string
     */
    protected function getSvLogOperationType()
    {
        $type = $this->isUpdate() ? 'edit' : 'new';
        if ($type === 'edit' && !$this->getExistingValue('is_expired') && $this->is_expired)
        {
            $type = 'expire';
        }

        if (Globals::$expiringFromCron && $type === 'expire' && empty(\XF::options()->svReportImpro_logNaturalWarningExpiry))
        {
            return null;
        }

        return $type;
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
        }

        if ($this->getOption('svLogWarningChanges'))
        {
            $type = $this->getSvLogOperationType();
            if ($type)
            {
                /** @var \SV\ReportImprovements\XF\Repository\Warning $warningRepo */
                $warningRepo = $this->repository('XF:Warning');
                $warningRepo->logOperation($this, $type, (bool)$this->getOption('svResolveReport'));
            }
        }

        parent::_postSave();
    }

    /**
     * @throws \Exception
     */
    protected function _postDelete()
    {
        parent::_postDelete();

        if ($this->getOption('svLogWarningChanges'))
        {
            /** @var \SV\ReportImprovements\XF\Repository\Warning $warningRepo */
            $warningRepo = $this->repository('XF:Warning');
            $warningRepo->logOperation($this, 'delete', (bool)$this->getOption('svResolveReport'));
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
        ];

        $structure->options['svLogWarningChanges'] = true;
        $structure->options['svResolveReport'] = false;

        return $structure;
    }
}