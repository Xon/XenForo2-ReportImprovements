<?php

namespace SV\ReportImprovements\XF\Pub\View\Member;

use SV\ReportImprovements\XF\Repository\Warning as WarningRepo;
use SV\WarningImprovements\XF\Entity\WarningDefinition;

/**
 * @extends \XF\Pub\View\Member\WarnFill
 */
class WarnFill extends XFCP_WarnFill
{
    public function renderJson()
    {
        $response = parent::renderJson();

        /** @var WarningDefinition $warningDefinition */
        $warningDefinition = $this->params['definition'];
        /** @var WarningRepo $repo */
        $repo = \XF::repository('XF:Warning');
        $value = $repo->getReplyBanForWarningDefinition($warningDefinition->warning_definition_id ?? 0);
        $response['formValues']["input[name='ban_length'][value='{$value}']"] = 1;

        return $response;
    }
}