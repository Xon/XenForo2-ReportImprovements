<?php

namespace SV\ReportImprovements\Listener;

use XF\BbCode\RuleSet;

/** @deprecated  */
abstract class bbCode
{
    /** @deprecated  */
    public static $bbCodeToDisable = [];

    /** @deprecated  */
    public static function bbCodeRules(RuleSet $ruleSet, ?string $context, ?string $subContext): void
    {
    }
}