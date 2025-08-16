<?php

namespace SV\ReportImprovements\XF;

use XF\Entity\User as UserEntity;
use XF\PermissionCache;

abstract class PermissionCacheEx extends PermissionCache
{
    public static function getOrLoadForContent(UserEntity $user, string $contentType, int $contentId): array
    {
        if ($contentId === 0)
        {
            return [];
        }

        $permissionCombinationId = $user->permission_combination_id;
        $permissionCache = $user->PermissionSet->getPermissionCache();
        $perms = $permissionCache->contentPerms[$permissionCombinationId][$contentType] ?? [];

        if (array_key_exists($contentId, $perms) || ($permissionCache->globalCacheRun[$contentType][$permissionCombinationId] ?? false))
        {
            return $perms;
        }

        $permissionCache->cacheAllContentPerms($permissionCombinationId, $contentType);

        return $permissionCache->contentPerms[$permissionCombinationId][$contentType] ?? [];
    }
}