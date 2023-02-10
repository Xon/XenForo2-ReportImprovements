<?php
/**
 * @noinspection PhpMultipleClassDeclarationsInspection
 */

namespace SV\ReportImprovements\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * Extends \XF\Entity\Search
 */
class Search extends XFCP_Search
{
    protected function setupConstraintFields(): void
    {
        parent::setupConstraintFields();

        $this->svUserConstraint[] = 'warning_user';
    }
}