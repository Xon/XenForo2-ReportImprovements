<?php
/**
 * @noinspection PhpFullyQualifiedNameUsageInspection
 * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
 */

namespace SV\ReportImprovements\XF\Repository;

/** @noinspection PhpUndefinedClassInspection */
\SV\StandardLib\Helper::repo()->aliasClass(
    SearchPatch::class,
    \XF::$versionId < 2030000
        ? \SV\ReportImprovements\XF\Repository\XF22\SearchPatch::class
        : \SV\ReportImprovements\XF\Repository\XF23\SearchPatch::class
);
