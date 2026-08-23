<?php

namespace SV\ReportImprovements\XF\BbCode\Renderer;

use function is_array;

/**
 * @extends \XF\BbCode\Renderer\Html
 */
class Html extends XFCP_Html
{
    // todo bimg tag

    public function renderTagMedia(array $children, $option, array $tag, array $options)
    {
        if (!empty($options['svDisableUrlMediaTag']))
        {
            return $this->renderUnparsedTag($tag, $options);
        }

        return parent::renderTagMedia($children, $option, $tag, $options);
    }

    public function renderTagImage(array $children, $option, array $tag, array $options)
    {
        if (!empty($options['svDisableUrlMediaTag']))
        {
            return $this->renderTagUrl($children, $option, $tag, $options);
        }

        return parent::renderTagImage($children, $option, $tag, $options);
    }

    public function renderTagUrl(array $children, $option, array $tag, array $options)
    {
        if (!empty($options['svDisableUrlMediaTag']) && is_array($option) && isset($option['media']))
        {
            unset($option['media']);
        }

        return parent::renderTagUrl($children, $option, $tag, $options);
    }
}