<?php

namespace SV\ReportImprovements\Option;

use SV\StandardLib\Helper;
use XF\Entity\Option as OptionEntity;
use XF\Entity\WarningDefinition as WarningDefinitionEntity;
use XF\Finder\WarningDefinition as WarningDefinitionFinder;
use XF\Option\AbstractOption;

class Warnings extends AbstractOption
{
    protected static function getSelectData(OptionEntity $option, array $htmlParams)
    {
        $finder = Helper::finder(WarningDefinitionFinder::class);
        if (isset($finder->getStructure()->columns['sv_display_order']))
        {
            $finder->with('Category')
                   ->order('Category.display_order')
                   ->order('sv_display_order');
        }
        $warnings = $finder->fetch();
        $choices = [];

        foreach ($warnings as $warningDefinitionId => $warningDefinition)
        {
            /** @var WarningDefinitionEntity $warningDefinition */
            $choices[$warningDefinitionId] = [
                '_type' => 'option',
                'value' => $warningDefinitionId,
                'label' => $warningDefinition->title
            ];
        }

        return [
            'choices' => $choices,
            'controlOptions' => self::getControlOptions($option, $htmlParams),
            'rowOptions' => self::getRowOptions($option, $htmlParams)
        ];
    }

    /**
     * @param OptionEntity $option
     * @param array        $htmlParams
     * @return string
     */
    public static function renderOption(OptionEntity $option, array $htmlParams)
    {
        $data = self::getSelectData($option, $htmlParams);
        $data['controlOptions']['multiple'] = true;
        $data['controlOptions']['size'] = 8;

        return self::getTemplater()->formSelectRow(
            $data['controlOptions'], $data['choices'], $data['rowOptions']
        );
    }

    /**
     * @param array $choices
     * @return bool
     */
    public static function verifyOption(array &$choices)
    {
        $choices = \array_unique(\array_map('intval', $choices));

        return true;
    }
}
