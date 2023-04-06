<?php

namespace SV\ReportImprovements\Cli\Command\Rebuild;

use SV\ReportImprovements\Job\RebuildCommentCount;
use Symfony\Component\Console\Input\InputOption;
use XF\Cli\Command\Rebuild\AbstractRebuildCommand;

class CommentCount extends AbstractRebuildCommand
{
    protected function getRebuildName(): string
    {
        return 'sv-report-comment-count';
    }

    protected function getRebuildDescription(): string
    {
        return 'Rebuild report comment counts. Does not trigger a re-index';
    }

    protected function getRebuildClass(): string
    {
        return RebuildCommentCount::class;
    }
}