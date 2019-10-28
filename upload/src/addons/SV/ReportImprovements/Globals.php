<?php

namespace SV\ReportImprovements;

/**
 * Class Globals
 *
 * @package SV\ReportImprovements
 */
class Globals
{
    /**
     * @var bool
     */
    public static $reportInAccountPostings = true;

    /**
     * @var null|bool
     */
    public static $expiringFromCron;

    /**
     * @var null|bool
     */
    public static $allowSavingReportComment;

    /** @var bool  */
    public static $suppressReportStateChange = false;

    /** @var bool  */
    public static $shimCommentsFinder = false;
}