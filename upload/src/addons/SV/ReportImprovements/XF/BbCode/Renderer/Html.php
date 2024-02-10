<?php

namespace SV\ReportImprovements\XF\BbCode\Renderer;

use function is_array;

/**
 * @extends \XF\BbCode\Renderer\Html
 */
class Html extends XFCP_Html
{
    public function renderTagUrl(array $children, $option, array $tag, array $options)
    {
        if (!empty($options['svDisableUrlMediaTag']) && is_array($option) && isset($option['media']))
        {
            unset($option['media']);
        }

        return parent::renderTagUrl($children, $option, $tag, $options);
    }
}