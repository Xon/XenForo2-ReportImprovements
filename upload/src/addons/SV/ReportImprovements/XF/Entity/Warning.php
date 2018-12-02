<?php

namespace SV\ReportImprovements\XF\Entity;

use SV\ReportImprovements\Globals;
use XF\Mvc\Entity\Structure;

/**
 * Class Warning
 *
 * Extends \XF\Entity\Warning
 *
 * @package SV\ReportImprovements\XF\Entity
 *
 * RELATIONS
 * @property \SV\ReportImprovements\XF\Entity\Report Report
 */
class Warning extends XFCP_Warning
{
    /**
     * @throws \Exception
     */
    protected function _postSave()
    {
        parent::_postSave();

        if ($this->isUpdate())
        {
            $type = 'edit';
            if (!$this->getExistingValue('is_expired') && $this->is_expired)
            {
                $type = 'expire';
            }

            if (Globals::$expiringFromCron === true && $type === 'expire' && !$this->app()->options()->sv_ri_log_to_report_natural_warning_expire)
            {
                return;
            }

            if ($type === 'edit')
            {
                $newValues = $this->getNewValues();
                if (isset($newValues['sv_acknowledgement']) || isset($newValues['sv_acknowledgement_date']))
                {
                    return;
                }
            }

            /** @var \SV\ReportImprovements\XF\Repository\Warning $warningRepo */
            $warningRepo = $this->repository('XF:Warning');
            $warningRepo->logOperation($this, $type);
        }
        else if ($this->isInsert())
        {
            /** @var \SV\ReportImprovements\XF\Repository\Warning $warningRepo */
            $warningRepo = $this->repository('XF:Warning');
            $warningRepo->logOperation($this, 'new');
        }
    }

    /**
     * @throws \Exception
     */
    protected function _postDelete()
    {
        parent::_postDelete();

        /** @var \SV\ReportImprovements\XF\Repository\Warning $warningRepo */
        $warningRepo = $this->repository('XF:Warning');
        $warningRepo->logOperation($this, 'delete');
    }

    /**
     * @param Structure $structure
     *
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->relations['Report'] = [
            'entity' => 'XF:Report',
            'type' => self::TO_ONE,
            'conditions' => [
                ['content_type', '=', '$content_type'],
                ['content_id', '=', '$content_id']
            ]
        ];

        return $structure;
    }
}