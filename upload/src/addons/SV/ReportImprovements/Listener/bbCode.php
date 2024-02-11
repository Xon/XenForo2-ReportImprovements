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

    public static function bbCodeRules(RuleSet $ruleSet, ?string $context, ?string $subContext): void
    {
        if ($context === null)
        {
            // even with a context/hint set, this method can still be called with a null context
            // https://xenforo.com/community/threads/unexpected-hinted-bb_code_rules-code-event-listener-triggered-without-a-hint.219335/
            return;
        }

        if ($subContext !== 'report' && (\XF::options()->svDisableEmbedsInUserReports ?? true))
        {
            foreach (static::$bbCodeToDisable as $tag)
            {
                $ruleSet->removeTag($tag);
            }
        }
    }
}