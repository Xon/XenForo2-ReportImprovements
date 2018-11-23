<?php

namespace SV\ReportImprovements\XF\Service\User;

use XF\Entity\Warning;
use XF\Mvc\Entity\Entity;

/**
 * Class Warn
 *
 * Extends \XF\Service\User\Warn
 *
 * @package SV\ReportImprovements\XF\Service\User
 */
class Warn extends XFCP_Warn
{
    /**
     * @return \SV\ReportImprovements\XF\Entity\Warning|Warning|Entity
     */
    protected function _save()
    {
        /** @var \SV\ReportImprovements\XF\Entity\Warning $warning */
        $warning = parent::_save();

        if ($warning)
        {

        }

        return $warning;
    }
}