<?php

namespace SV\ReportImprovements\XF;

use XF\PermissionCache;

abstract class PermissionCacheEx extends PermissionCache
{
    public static function getOrLoadForContent(int $permissionCombinationId, string $contentType, int $contentId): array
    {
        if ($contentId === 0)
        {
            return [];
        }

        $permissionCache = \XF::permissionCache();
        $perms = $permissionCache->contentPerms[$permissionCombinationId][$contentType] ?? [];

        if (array_key_exists($contentId, $perms) || ($permissionCache->globalCacheRun[$contentType][$permissionCombinationId] ?? false))
        {
            return $perms;
        }

        $permissionCache->cacheAllContentPerms($permissionCombinationId, $contentType);

        return $permissionCache->contentPerms[$permissionCombinationId][$contentType] ?? [];
    }
}