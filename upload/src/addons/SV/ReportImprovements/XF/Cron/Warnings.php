<?php

namespace SV\ReportImprovements\XF\Cron;

use SV\ReportImprovements\Globals;

/**
 * Class Warnings
 * 
 * Extends \XF\Cron\Warnings
 *
 * @package SV\ReportImprovements\XF\Cron
 */
class Warnings extends XFCP_Warnings
{
    public static function expireWarnings()
    {
        Globals::$expiringFromCron = true;

        try
        {
            parent::expireWarnings();
        }
        finally
        {
            Globals::$expiringFromCron = null;
        }
    }
}