<?php

namespace SV\ReportImprovements\Entity;

use SV\StandardLib\Helper;
use SV\WarningImprovements\XF\Entity\WarningDefinition as ExtendedWarningDefinitionEntity;
use SV\WarningImprovements\XF\Repository\Warning as ExtendedWarningRepo;
use XF\Repository\Warning as WarningRepo;
use function assert;

trait WarningInfoTrait
{
    public function canShowDefinition(): bool
    {
        $visitor = \XF::visitor();
        if (!$visitor->canViewWarnings())
        {
            return false;
        }

        if ($this->definition_title === null)
        {
            return false;
        }

        if (Helper::isAddOnActive('SV/WarningImprovements'))
        {
            $definition = $this->Definition;
            assert($definition instanceof ExtendedWarningDefinitionEntity);

            return $definition->isUsable();
        }

        return true;
    }

    protected function getDefinitionTitle(): ?string
    {
        if ($this->warning_definition_id === 0)
        {
            if (Helper::isAddOnActive('SV/WarningImprovements'))
            {
                /** @var ExtendedWarningRepo $warningRepo */
                $warningRepo = Helper::repository(WarningRepo::class);
                return $warningRepo->getCustomWarningDefinition()->title;
            }

            return (string)\XF::phrase('custom_warning');
        }

        $definition_ = $this->Definition_;
        if ($definition_ === null)
        {
            return $definition_;
        }

        return $definition_->title->render('raw');
    }
}