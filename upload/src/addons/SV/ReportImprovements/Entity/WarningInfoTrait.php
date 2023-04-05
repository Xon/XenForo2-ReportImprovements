<?php

namespace SV\ReportImprovements\Entity;

use SV\WarningImprovements\XF\Entity\WarningDefinition as ExtendedWarningDefinitionEntity;
use XF\Phrase;
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

        if (\XF::isAddOnActive('SV/WarningImprovements'))
        {
            $definition = $this->Definition;
            assert($definition instanceof ExtendedWarningDefinitionEntity);

            return $definition->isUsable();
        }

        return true;
    }

    protected function getDefinitionTitle(): ?Phrase
    {
        if ($this->warning_definition_id === 0)
        {
            if (\XF::isAddOnActive('SV/WarningImprovements'))
            {
                /** @var \SV\WarningImprovements\XF\Repository\Warning $warningRepo */
                $warningRepo = $this->repository('XF:Warning');
                return $warningRepo->getCustomWarningDefinition()->title;
            }

            return \XF::phrase('custom_warning');
        }

        return $this->Definition_->title ?? null;
    }
}