<?php

namespace SV\ReportImprovements\Cli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function count;

class ExportReportUsers extends Command
{
    protected function configure()
    {
        $this
            ->setName('sv-dev:export-report-users')
            ->setDescription('Export a list of users who look to have access to a report')
            ->addArgument(
                'report-id',
                InputArgument::REQUIRED,
                'The report to export users for'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $id = $input->getArgument('report-id');
        $report = \XF::app()->find('XF:Report', $id);
        if ($report === null)
        {
            $output->writeln("<error>Report not found.</error>");
            return 1;
        }
        assert($report instanceof \XF\Entity\Report);
        $reportRepo = \XF::repository('XF:Report');
        assert($reportRepo instanceof \SV\ReportImprovements\XF\Repository\Report);

        \XF::options()->offsetSet('svNonModeratorReportHandlingLimit', 0);
        $userIds = $reportRepo->getNonModeratorsWhoCanHandleReport($report, false);
        if (count($userIds) === 0)
        {
            $output->writeln("No non-moderator users detected for report {$report->report_id}.");
            return 0;
        }
        $db = \XF::app()->db();
        $usernames = $db->fetchAllColumn("select username from xf_user where user_id in ({$db->quote($userIds)})");
        if (count($usernames) === 0)
        {
            $output->writeln("No non-moderator users detected for report {$report->report_id}.");
            return 0;
        }

        $output->writeln("List of users who have some permissions assigned for report {$report->report_id}:");
        foreach($usernames as $username)
        {
            $output->writeln($username);
        }

        return 0;
    }
}