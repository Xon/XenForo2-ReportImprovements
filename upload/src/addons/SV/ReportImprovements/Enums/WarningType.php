<?php

namespace SV\ReportImprovements\Enums;

// One day this will be an enum
class WarningType
{
    public const New         = 'new';
    public const Edit        = 'edit';
    public const Expire      = 'expire';
    public const Delete      = 'delete';
    public const Acknowledge = 'acknowledge';

    /**
     * Used in installer
     * @return string[]
     */
    public static function getAll(): array
    {
        return [self::New, self::Edit, self::Expire, self::Delete, self::Acknowledge];
    }

    public static function get(): array
    {
        $types = [self::New, self::Edit, self::Expire, self::Delete];
        if (\XF::isAddOnActive('SV/WarningAcknowledgement'))
        {
            $types[] = self::Acknowledge;
        }

        return $types;
    }

    /**
     * @return array<string,\XF\Phrase>
     */
    public static function getPairs(): array
    {
        $pairs = [];

        foreach (static::get() as $type)
        {
            $pairs[$type] = \XF::phrase('svReportImprov_warning_type.' . $type);
        }

        return $pairs;
    }
}