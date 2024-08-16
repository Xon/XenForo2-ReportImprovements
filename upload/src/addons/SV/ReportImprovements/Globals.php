<?php

namespace SV\ReportImprovements;

abstract class Globals
{
    /** @var bool */
    public static $resolveReplyBanOnDelete = false;

    /**
     * @var null|bool
     */
    public static $expiringFromCron;

    /**
     * @var null|bool
     */
    public static $forceSavingReportComment;

    /** @var bool  */
    public static $suppressReportStateChange = false;

    /** @var bool  */
    public static $shimCommentsFinder = false;

    /** @var int[] */
    public static $notifyReportUserIds = [];

    private function __construct() { }
}