<?php
/**
 * @noinspection PhpMissingParentCallCommonInspection
 */

namespace SV\ReportImprovements\Cli\Command;

use SV\ReportImprovements\XF\Repository\Report as ExtendedReportRepo;
use SV\StandardLib\Helper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use XF\Entity\Report as ReportEntity;
use XF\Repository\Report as ReportRepo;
use function assert;
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
            )->addOption(
                'cache', null, InputOption::VALUE_OPTIONAL,'Fetch user list from cache', true
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getArgument('report-id');
        $report = Helper::find(ReportEntity::class, $id);
        if ($report === null)
        {
            $output->writeln('<error>Report not found.</error>');

            return 1;
        }
        $reportRepo = Helper::repository(ReportRepo::class);
        assert($reportRepo instanceof ExtendedReportRepo);

        $cache = (bool)$input->getOption('cache');

        if ($cache)
        {
            $output->writeln('Fetching from cache');
        }
        else
        {
            $output->writeln('Skipping cache');
        }

        \XF::options()->offsetSet('svReportHandlingLimit', 0);
        $userIds = $reportRepo->svGetUsersWhoCanHandleReport($report, $cache);
        if (count($userIds) === 0)
        {
            $output->writeln("No users detected for report: {$report->report_id}");

            return 0;
        }
        $db = \XF::db();
        $usernames = $db->fetchAllColumn("
            select username 
            from xf_user 
            where user_id in ({$db->quote($userIds)}) and is_moderator = 1
            order by username
        ");
        if (count($usernames) === 0)
        {
            $output->writeln("No moderators detected for report ({$report->report_id})");
        }
        else
        {
            $output->writeln("List of moderators who have some permissions assigned for report ({$report->report_id}):");
            foreach ($usernames as $username)
            {
                $output->writeln($username);
            }
            $output->writeln('');
        }

        $usernames = $db->fetchAllColumn("
            select username 
            from xf_user 
            where user_id in ({$db->quote($userIds)}) and is_moderator = 0 
            order by username
        ");
        if (count($usernames) === 0)
        {
            $output->writeln("No non-moderators detected for report ({$report->report_id})");
        }
        else
        {
            $output->writeln("List of non-moderators who have some permissions assigned for report ({$report->report_id}):");
            foreach ($usernames as $username)
            {
                $output->writeln($username);
            }
            $output->writeln('');
        }

        return 0;
    }
}