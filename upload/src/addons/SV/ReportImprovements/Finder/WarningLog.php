<?php

namespace SV\ReportImprovements\Finder;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\AbstractCollection as AbstractCollection;
use SV\ReportImprovements\Entity\WarningLog as WarningLogEntity;

/**
 * @method AbstractCollection<WarningLogEntity>|WarningLogEntity[] fetch(?int $limit = null, ?int $offset = null)
 * @method WarningLogEntity|null fetchOne(?int $offset = null)
 * @implements \IteratorAggregate<string|int,WarningLogEntity>
 * @extends Finder<WarningLogEntity>
 */
class WarningLog extends Finder
{

}