<?php

namespace SV\ReportImprovements\Enums;

// One day this will be an enum
class ReportType
{
    public const Reported_content = 'reported_content';
    public const User_report = 'user_report';
    public const Comment = 'comment';
    public const Warning = 'warning';
    public const Reply_ban = 'reply_ban';


    public static function get(): array
    {
        return [self::Reported_content, self::User_report, self::Comment, self::Warning, self:: Reply_ban];
    }

    /**
     * @return array<string,\XF\Phrase>
     */
    public static function getPairs(): array
    {
        $pairs = [];

        foreach (static::get() as $type)
        {
            $pairs[$type] = \XF::phrase('svReportImprov_report_type.' . $type);
        }

        return $pairs;
    }
}