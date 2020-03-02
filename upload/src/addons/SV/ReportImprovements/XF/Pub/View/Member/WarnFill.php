<?php

namespace SV\ReportImprovements\XF\Pub\View\Member;

/**
 * Extends \XF\Pub\View\Member\WarnFill
 */
class WarnFill extends XFCP_WarnFill
{
    public function renderJson()
    {
        $response = parent::renderJson();

        /** @var \SV\WarningImprovements\XF\Entity\WarningDefinition $warningDefinition */
        $warningDefinition = $this->params['definition'];
        /** @var \SV\ReportImprovements\XF\Repository\Warning $repo */
        $repo = \XF::repository('XF:Warning');
        $value = $repo->getReplyBanForWarningDefinition($warningDefinition);
        $response['formValues']["input[name='ban_length'][value='{$value}']"] = 1;

        return $response;
    }
}