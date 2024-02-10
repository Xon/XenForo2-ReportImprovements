<?php

namespace SV\ReportImprovements\Listener;

use XF\BbCode\RuleSet;

abstract class bbCode
{
    public static $bbCodeToDisable = [
        'img',
        'bimg',
        'embed',
        'media',
    ];

    /** @noinspection PhpUnusedParameterInspection */
    public static function bbCodeRules(RuleSet $ruleSet, string $context, string $subContext): void
    {
        if ($subContext === 'report' && (\XF::options()->svDisableEmbedsInUserReports ?? true))
        {
            foreach (static::$bbCodeToDisable as $tag)
            {
                $ruleSet->removeTag($tag);
            }
        }
    }
}