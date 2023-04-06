<?php

namespace SV\ReportImprovements\Cli\Command\Rebuild;

use SV\ReportImprovements\Job\RebuildWarningLogLatestVersion;
use Symfony\Component\Console\Input\InputOption;
use XF\Cli\Command\Rebuild\AbstractRebuildCommand;

class WarningLogLastVersion extends AbstractRebuildCommand
{
    protected function getRebuildName(): string
    {
        return 'sv-warning-log-latest-version';
    }

    protected function getRebuildDescription(): string
    {
        return 'Rebuild the latest version flag on the warning-log which is used in search for "only search latest version" of warnings';
    }

    protected function getRebuildClass(): string
    {
        return RebuildWarningLogLatestVersion::class;
    }

    protected function configureOptions(): void
    {
        parent::configureOptions();

        $this
            ->addOption(
                'reindex',
                null,
                InputOption::VALUE_NONE,
                'Reindex for search if entities need updating'
            )
        ;
    }
}