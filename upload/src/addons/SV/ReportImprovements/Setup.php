<?php /** @noinspection RedundantSuppression */

namespace SV\ReportImprovements;

use SV\ReportImprovements\Enums\WarningType;
use SV\ReportImprovements\Job\RebuildCommentCount;
use SV\ReportImprovements\Job\RebuildWarningLogLatestVersion;
use SV\ReportImprovements\Job\Upgrades\EnrichReportPostInstall;
use SV\ReportImprovements\Job\Upgrades\Upgrade1090100Step1;
use SV\ReportImprovements\Job\Upgrades\Upgrade1090200Step1;
use SV\ReportImprovements\Job\WarningLogMigration;
use SV\ReportImprovements\Repository\ReportQueue as ReportQueueRepo;
use SV\StandardLib\InstallerHelper;
use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;
use XF\Entity\Moderator;
use XF\Entity\ModeratorContent;
use XF\Entity\Phrase;
use XF\Entity\ReportComment;
use XF\Entity\User;
use XF\Entity\UserAlert;
use XF\Job\Atomic as AtomicJob;
use XF\Job\PermissionRebuild;
use XF\Job\PermissionRebuildPartial;
use XF\Repository\PermissionCombination;
use XF\Repository\PermissionEntry;
use XF\Service\UpdatePermissions;
use function array_keys;
use function array_values;
use function assert;
use function count;
use function sort;

/**
 * Class Setup
 *
 * @package SV\ReportImprovements
 */
class Setup extends AbstractSetup
{
    use InstallerHelper;
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    public function installStep1()
    {
        $this->applySchemaNewTables();
    }

    public function installStep2()
    {
        $this->applySchemaUpdates();
    }

    public function upgrade1090100Step1()
    {
        $this->app->jobManager()->enqueueUnique(
            'svRIUpgrade1090100Step1',
            Upgrade1090100Step1::class
        );
    }

    public function upgrade1090200Step1()
    {
        $this->app->jobManager()->enqueueUnique(
            'svRIUpgrade1090200Step1',
            Upgrade1090200Step1::class
        );
    }

    public function upgrade2000001Step1()
    {
        $this->db()->query('
          UPDATE xf_report_comment
          SET warning_log_id = NULL
          WHERE warning_log_id = 0
        ');
    }

    public function upgrade2000002Step1()
    {
        $this->applySchemaNewTables();
    }

    public function upgrade2000002Step2()
    {
        $this->applySchemaUpdates();
    }

    public function upgrade2000002Step4()
    {
        /** @noinspection SqlResolve */
        $this->db()->query('
          UPDATE xf_sv_warning_log
          SET points = NULL, warning_definition_id = NULL
          WHERE reply_ban_thread_id <> 0 AND points = 0
        ');
    }

    public function upgrade2000002Step5()
    {
        /** @noinspection SqlResolve */
        $this->db()->query('
          UPDATE xf_sv_warning_log
          SET reply_ban_thread_id = NULL
          WHERE reply_ban_thread_id = 0
        ');
    }

    public function upgrade2000002Step6()
    {
        /** @noinspection SqlResolve */
        $this->db()->query('
          UPDATE xf_sv_warning_log
          SET reply_ban_post_id = NULL
          WHERE reply_ban_post_id = 0
        ');
    }

    public function upgrade2010400Step1()
    {
        /** @noinspection SqlResolve */
        /** @noinspection SqlWithoutWhere */
        $this->db()->query('
          UPDATE xf_report
          SET last_modified_id = COALESCE((SELECT report_comment_id 
                                  FROM xf_report_comment 
                                  WHERE xf_report_comment.report_id = xf_report.report_id
                                  ORDER BY comment_date DESC
                                  LIMIT 1), 0)
        ');
    }

    public function upgrade2020200Step1()
    {
        $this->renamePhrases([
            'svReportImprov_thread_reply_ban'           => 'svReportImprov_operation_type_action.reply_ban',
            'svReportImprov_thread_reply_ban_from_post' => 'svReportImprov_operation_type_action.reply_ban_from_post',
        ]);
    }

    public function upgrade2020700Step1(array $stepParams)
    {
        $finder = \XF::finder('XF:UserAlert')
                     ->where('content_type', '=', 'report_comment')
                     ->where('action', '=', 'mention')
                     ->order('alert_id');

        $stepData = $stepParams[2] ?? [];
        if (!isset($stepData['max']))
        {
            $stepData['max'] = $finder->total();
        }
        $alerts = $finder->limit(50)->fetch();
        if (!$alerts->count())
        {
            return null;
        }

        $next = $stepParams[0] ?? 0;
        foreach ($alerts as $alert)
        {
            $next++;
            /** @var UserAlert $alert */
            $extraData = $alert->extra_data;
            /** @var ReportComment $comment */
            $comment = \XF::finder('XF:ReportComment')->whereId($alert->content_id)->fetchOne();
            if (!$comment)
            {
                continue;
            }
            $extraData['comment'] = $comment->toArray();

            $alert->content_type = 'report';
            $alert->content_id = $comment->report_id;
            $alert->extra_data = $extraData;

            $alert->save();
        }

        return [
            $next,
            "{$next} / {$stepData['max']}",
            $stepData
        ];
    }

    public function upgrade2050004Step1()
    {
        $this->migrateTableToReactions('xf_report_comment');
    }

    public function upgrade2050004Step2()
    {
        $this->renameLikeAlertOptionsToReactions('report_comment');
    }

    public function upgrade2050004Step3()
    {
        $this->renameLikeAlertsToReactions('report_comment');
    }

    public function upgrade2050004Step4()
    {
        $this->renameLikePermissionsToReactions([
            'general' => false // global only
        ], 'reportLike', 'reportReact');
    }

    public function upgrade2050004Step5()
    {
        $this->renameLikeStatsToReactions(['report', 'report_comment']);
    }

    public function upgrade2050004Step6()
    {
        $this->renamePhrases([
            'push_x_reacted_to_your_comment_on_your_report' => 'svReportImprov_push_x_reacted_to_your_comment_on_your_report',
            'push_x_reacted_to_your_comment_on_ys_report' => 'svReportImprov_push_x_reacted_to_your_comment_on_ys_report',
        ]);
    }

    public function upgrade2050004Step7()
    {
        \XF::db()->query("
            UPDATE xf_user_alert 
            SET depends_on_addon_id = 'SV/ReportImprovements'
            WHERE depends_on_addon_id = '' AND content_type = 'report_comment' AND action IN ('insert', 'reaction')
        ");
    }

    public function upgrade2050100Step1()
    {
        $this->applySchemaNewTables();
    }

    public function upgrade2050100Step2()
    {
        $this->applySchemaUpdates();
    }

    public function upgrade2070000Step1()
    {
        $this->renameOption('sv_ri_user_id', 'svReportImpro_expireUserId');
        $this->renameOption('sv_ri_log_to_report_natural_warning_expire', 'svReportImpro_logNaturalWarningExpiry');
        $this->renameOption('sv_ri_expiry_days', 'svReportImpro_autoExpireDays');
        $this->renameOption('sv_ri_expiry_action', 'svReportImpro_autoExpireACtion');
    }

    public function upgrade2070100Step1()
    {
        $this->applySchemaUpdates();
    }

    public function upgrade2100000Step1()
    {
        $this->applySchemaNewTables();
    }

    public function upgrade2100000Step2()
    {
        $this->applySchemaUpdates();
    }

    public function upgrade2100000Step3()
    {
        $permissions = [
            'assignReport',
            'replyReport',
            'replyReportClosed',
            'reportReact',
            'updateReport',
            'viewReporterUsername',
            'viewReportUser',
        ];

        $db = $this->db();

        $db->query('update xf_permission_entry
            set permission_group_id = ?
            where permission_group_id = ? and permission_id in (' . $db->quote($permissions) . ')
        ', ['report_queue', 'general']);

        $db->query('update xf_permission_entry
            set permission_group_id = ?
            where permission_group_id = ? and permission_id  = ?
        ', ['report_queue', 'profilePost', 'viewReportProfilePost']);

        $db->query('update xf_permission_entry
            set permission_group_id = ?
            where permission_group_id = ? and permission_id = ?
        ', ['report_queue', 'conversation', 'viewReportConversation']);
    }

    public function upgrade2100100Step1()
    {
        $this->applySchemaNewTables();
    }

    public function upgrade2101200Step1()
    {
        $this->db()->query('
            DELETE modLog
            FROM xf_moderator_log AS modLog
            JOIN xf_report_comment as reportComment ON modLog.content_id = reportComment.report_comment_id
            WHERE modLog.content_type = \'report_comment\' AND 
                  modLog.action = \'edit\' AND
                  modLog.log_date = reportComment.comment_date 
        ');
    }

    public function upgrade2140005Step1(): void
    {
        $this->applySchemaUpdates();
    }

    public function upgrade2140005Step2(): void
    {
        $this->db()->query('
            UPDATE xf_report_comment
            SET warning_log_id = NULL
            WHERE warning_log_id = 0
        ');
    }

    public function upgrade2140005Step3(): void
    {
        $this->renamePhrases([
            'svReportImprov_search_reports' => 'svReportImprov_search.reports',
            'svReportImprove_content_in' => 'svReportImprove_reported_content_in',
        ]);
    }

    public function upgrade1680418222Step1(): void
    {
        $this->customizeWarningLogContentTypePhrases();
    }

    public function upgrade1680614325Step1(): void
    {
        $this->applySchemaNewTables();
    }

    public function upgrade1680614325Step2(): void
    {
        $this->schemaManager()->alterTable('xf_sv_warning_log', function (Alter $table) {
            $table->dropIndexes([
                'content_type_id',
                'user_id_date',
                'expiry',
                'operation_type',
                'warning_edit_date',
                'reply_ban_thread_id_warning_edit_date',
                'warning_id_warning_edit_date',
            ]);
        });
    }

    public function upgrade1680614327Step1(): void
    {
        $this->renameOption('svNonModeratorReportHandlingLimit', 'svReportHandlingLimit');
    }

    public function upgrade1683897241Step1(): void
    {
        $this->applySchemaNewTables();
    }

    public function upgrade1683897241Step2(): void
    {
        $this->db()->query('
            UPDATE xf_sv_warning_log
            SET warning_definition_id = NULL
            WHERE warning_definition_id = 0
        ');
    }

    public function upgrade1693830360Step1(): void
    {
        $this->applySchemaNewTables();
    }

    public function upgrade1693830360Step2(): void
    {
        $this->applySchemaUpdates();
    }

    /**
     * Drops add-on tables.
     */
    public function uninstallStep1()
    {
        $sm = $this->schemaManager();

        foreach ($this->getTables() as $tableName => $callback)
        {
            $sm->dropTable($tableName);
        }
    }

    /**
     * Drops columns from core tables.
     */
    public function uninstallStep2()
    {
        $sm = $this->schemaManager();

        foreach ($this->getRemoveAlterTables() as $tableName => $callback)
        {
            if ($sm->tableExists($tableName))
            {
                $sm->alterTable($tableName, $callback);
            }
        }
    }

    protected function customizeWarningLogContentTypePhrases(): void
    {
        $map = [
            'warning'  => 'warning_log',
            'warnings' => 'warning_logs',
        ];
        $phrases = $this->app->finder('XF:Phrase')
                             ->where('language_id', '<>', 0)
                             ->where('title', array_keys($map))
                             ->fetch();
        foreach ($phrases as $stockPhrase)
        {
            assert($stockPhrase instanceof Phrase);
            $title = $map[$stockPhrase->title];

            $phrase = $this->app->finder('XF:Phrase')
                                ->where('language_id', '<>', 0)
                                ->where('title', $title)
                                ->fetchOne();
            if ($phrase === null)
            {
                $phrase = $this->app->em()->create('XF:Phrase');
                assert($phrase instanceof Phrase);
                $phrase->language_id = $stockPhrase->language_id;
                $phrase->title = $title;
            }

            $phrase->phrase_text = $stockPhrase->phrase_text;
            $phrase->addon_id = $this->addOn->getAddOnId();
            $phrase->version_id = $this->addOn->getJsonVersion()['version_id'];
            $phrase->version_string = $this->addOn->getJsonVersion()['version_string'];
            try
            {
                // this can still throw, but it should throw less
                $phrase->save(false);
            }
            catch (\Exception $e)
            {
                \XF::logException($e);
            }
        }
    }

    protected function applyPerms(int $versionId, array &$atomicJobs): void
    {
        if ($this->applyDefaultPermissions($versionId, $doFullRebuild))
        {
            if ($doFullRebuild)
            {
                $atomicJobs[] = PermissionRebuild::class;
            }
            else
            {
                $ids = $this->getPermissionCombinationIdsToRebuild();
                $atomicJobs[] = [PermissionRebuildPartial::class, ['combinationIds' => $ids]];
            }
        }
    }

    public function postInstall(array &$stateChanges)
    {
        parent::postInstall($stateChanges);

        $atomicJobs = [];
        $this->cleanupPermissionChecks();
        $this->customizeWarningLogContentTypePhrases();
        $this->applyPerms(0, $atomicJobs);

        $atomicJobs[] = EnrichReportPostInstall::class;
        $atomicJobs[] = Upgrade1090100Step1::class;
        $atomicJobs[] = Upgrade1090200Step1::class;
        $atomicJobs[] = WarningLogMigration::class;

        if (count($atomicJobs) !== 0)
        {
            \XF::app()->jobManager()->enqueueUnique(
                'report-improvements-installer',
                AtomicJob::class, ['execute' => $atomicJobs]
            );
        }
    }

    public function postUpgrade($previousVersion, array &$stateChanges)
    {
        $previousVersion = (int)$previousVersion;
        parent::postUpgrade($previousVersion, $stateChanges);

        $atomicJobs = [];
        $this->cleanupPermissionChecks();
        // updating permissions should be done first!
        $this->applyPerms($previousVersion, $atomicJobs);

        if ($previousVersion < 2140002)
        {
            $atomicJobs[] = EnrichReportPostInstall::class;
        }

        if ($previousVersion < 1680751722)
        {
            $atomicJobs[] = RebuildCommentCount::class;
            $atomicJobs[] = [RebuildWarningLogLatestVersion::class, ['reindex' => false]];
        }

        $atomicJobs[] = WarningLogMigration::class;

        if (count($atomicJobs) !== 0)
        {
            \XF::app()->jobManager()->enqueueUnique(
                'report-improvements-installer',
                AtomicJob::class, ['execute' => $atomicJobs]
            );
        }
    }

    public function postRebuild(): void
    {
        parent::postRebuild();

        $this->cleanupPermissionChecks();
    }

    protected function cleanupPermissionChecks()
    {
        /** @var PermissionEntry $permEntryRepo */
        $permEntryRepo = \XF::repository('XF:PermissionEntry');

        $permEntryRepo->deleteOrphanedGlobalUserPermissionEntries();
        $permEntryRepo->deleteOrphanedContentUserPermissionEntries();

        /** @var PermissionCombination $permComboRepo */
        $permComboRepo = \XF::repository('XF:PermissionCombination');
        $permComboRepo->deleteUnusedPermissionCombinations();

        $reportQueueRepo = \XF::repository('SV\ReportImprovements:ReportQueue');
        assert($reportQueueRepo instanceof ReportQueueRepo);
        $reportQueueRepo->resetNonModeratorsWhoCanHandleReportCache();
    }

    /**
     * @noinspection PhpDocMissingThrowsInspection
     * @param int       $previousVersion
     * @param bool|null $doFullRebuild
     * @return bool True if permissions were applied.
     */
    protected function applyDefaultPermissions(int $previousVersion = 0, ?bool &$doFullRebuild = null): bool
    {
        $doFullRebuild = false;
        $applied = false;
        $db = $this->db();
        $globalReportPerms = ['viewReports'];
        $globalReportQueuePerms = [
            'view', 'edit', 'viewAttachment','uploadAttachment', 'uploadVideo',
            'assignReport', 'replyReport', 'replyReportClosed', 'updateReport', 'viewReporterUsername', 'reportReact',
        ];
        $whiteListedGroups = [User::GROUP_MOD, User::GROUP_ADMIN];

        // content/global moderators before bulk update
        if (!$previousVersion || ($previousVersion <= 1040002) || ($previousVersion >= 2000000 && $previousVersion <= 2011000))
        {
            /** @var PermissionEntry $permissionEntryRepo */
            $permissionEntryRepo = \XF::repository('XF:PermissionEntry');
            /** @var \XF\Repository\Moderator $modRepo */
            $modRepo = \XF::repository('XF:Moderator');

            $contentModerators = $modRepo->findContentModeratorsForList()->fetch();
            /** @var ModeratorContent $contentModerator */
            foreach ($contentModerators as $contentModerator)
            {
                $user = $contentModerator->User;
                if (!$user)
                {
                    continue;
                }

                $permissions = $permissionEntryRepo->getContentUserPermissionEntries(
                    $contentModerator->content_type,
                    $contentModerator->content_id,
                    $contentModerator->user_id
                );
                if (!$permissions)
                {
                    continue;
                }
                $newPermissions = $permissions;
                if (!isset($newPermissions['forum']['viewReportPost']) &&
                    (!empty($newPermissions['forum']['editAnyPost']) || !empty($newPermissions['forum']['deleteAnyPost']) || !empty($newPermissions['forum']['warn']))
                )
                {
                    $newPermissions['forum']['viewReportPost'] = 'content_allow';
                }
                if (isset($newPermissions['forum']['viewReportPost']) && $globalReportQueuePerms)
                {
                    $globalPerms = $permissionEntryRepo->getGlobalUserPermissionEntries($user->user_id);
                    $newGlobalPerms = $globalPerms;
                    foreach ($globalReportPerms as $perm)
                    {
                        $newGlobalPerms['general'][$perm] = 'allow';
                    }
                    foreach ($globalReportQueuePerms as $perm)
                    {
                        $newGlobalPerms['report_queue'][$perm] = 'allow';
                    }

                    if ($newGlobalPerms !== $globalPerms)
                    {
                        /** @var UpdatePermissions $permissionUpdater */
                        $permissionUpdater = \XF::service('XF:UpdatePermissions');
                        $permissionUpdater->setUser($user);
                        $permissionUpdater->setGlobal();
                        $permissionUpdater->updatePermissions($newGlobalPerms);
                    }
                }
                /** @noinspection PhpConditionAlreadyCheckedInspection */
                if ($newPermissions != $permissions)
                {
                    /** @var UpdatePermissions $permissionUpdater */
                    $permissionUpdater = \XF::service('XF:UpdatePermissions');
                    $permissionUpdater->setUser($user);
                    $permissionUpdater->setContent($contentModerator->content_type, $contentModerator->content_id);
                    $permissionUpdater->updatePermissions($newPermissions);
                }
                \XF::triggerRunOnce();
            }

            $globalReportPermsChecks = [
                [
                    [
                        'general'      => ['warn', 'editBasicProfile'],
                        'conversation' => ['alwaysInvite', 'editAnyPost', 'viewAny'],
                        'profilePost'  => ['warn', 'editAnyPost', 'viewAny'],
                    ],
                    ['general' => $globalReportPerms]
                ],
                [
                    [
                        'general'      => ['warn', 'editBasicProfile'],
                        'conversation' => ['alwaysInvite', 'editAnyPost', 'viewAny'],
                        'profilePost'  => ['warn', 'editAnyPost', 'viewAny'],
                    ],
                    ['report_queue' => $globalReportQueuePerms]
                ],
                [
                    [
                        'general'      => ['warn', 'editBasicProfile'],
                    ],
                    ['report_queue' => ['viewReportUser']]
                ],
                [
                    [
                        'profilePost' => ['warn', 'editAnyPost', 'viewAny'],
                    ],
                    ['report_queue' => ['viewReportProfilePost']]
                ],
                [
                    [
                        'conversation' => ['alwaysInvite', 'editAnyPost', 'viewAny'],
                    ],
                    ['report_queue' => ['viewReportConversation']]
                ],
                [
                    [
                        'forum' => ['warn', 'editAnyPost', 'deleteAnyPost']
                    ],
                    ['forum' => ['viewReportPost']]
                ],
            ];

            $moderators = $modRepo->findModeratorsForList()->fetch();
            /** @var Moderator $moderator */
            foreach ($moderators as $moderator)
            {
                if (!$moderator->User)
                {
                    continue;
                }
                $permissions = $permissionEntryRepo->getGlobalUserPermissionEntries($moderator->user_id);
                if (!$permissions)
                {
                    continue;
                }
                $newPermissions = $permissions;
                foreach ($globalReportPermsChecks as $raw)
                {
                    [$checks, $assignments] = $raw;
                    foreach ($checks as $category => $permToTests)
                    {
                        if (isset($newPermissions[$category]))
                        {
                            foreach ($permToTests as $permToTest)
                            {
                                if (isset($newPermissions[$category][$permToTest]))
                                {
                                    // ensure access to report centre
                                    foreach ($assignments as $category => $perms)
                                    {
                                        foreach ($perms as $newPerm)
                                        {
                                            if (empty($newPermissions[$category][$newPerm]))
                                            {
                                                $newPermissions[$category][$newPerm] = 'allow';
                                            }
                                        }
                                    }
                                    break 2;
                                }
                            }
                        }
                    }
                }
                /** @noinspection PhpConditionAlreadyCheckedInspection */
                if ($newPermissions != $permissions)
                {
                    /** @var UpdatePermissions $permissionUpdater */
                    $permissionUpdater = \XF::service('XF:UpdatePermissions');
                    $permissionUpdater->setUser($moderator->User);
                    $permissionUpdater->setGlobal();
                    $permissionUpdater->updatePermissions($newPermissions);
                }
            }

            \XF::triggerRunOnce();
            $applied = true;
        }

        if (!$previousVersion)
        {
            $db->query(
                "INSERT IGNORE INTO xf_permission_entry_content (content_type, content_id, user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
                SELECT DISTINCT content_type, content_id, user_group_id, user_id, convert(permission_group_id USING utf8), 'viewReportPost', permission_value, permission_value_int
                FROM xf_permission_entry_content
                WHERE permission_group_id = 'forum' AND permission_id IN ('warn','editAnyPost','deleteAnyPost')
                    AND user_group_id in ({$db->quote($whiteListedGroups)})
            "
            );

            $db->query(
                "INSERT IGNORE INTO xf_permission_entry (user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
                SELECT DISTINCT user_group_id, user_id, convert(permission_group_id USING utf8), 'viewReportPost', permission_value, permission_value_int
                FROM xf_permission_entry
                WHERE permission_group_id = 'forum' AND permission_id IN ('warn','editAnyPost','deleteAnyPost')
                    AND user_group_id in ({$db->quote($whiteListedGroups)})
            "
            );
            $db->query(
                "INSERT IGNORE INTO xf_permission_entry (user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
                SELECT DISTINCT user_group_id, user_id, 'report_queue', 'viewReportConversation', permission_value, permission_value_int
                FROM xf_permission_entry
                WHERE permission_group_id = 'conversation' AND permission_id IN ('alwaysInvite','editAnyPost','viewAny')
                    AND user_group_id in ({$db->quote($whiteListedGroups)})
            "
            );
            $db->query(
                "INSERT IGNORE INTO xf_permission_entry (user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
                SELECT DISTINCT user_group_id, user_id, 'report_queue', 'viewReportProfilePost', permission_value, permission_value_int
                FROM xf_permission_entry
                WHERE permission_group_id = 'profilePost' AND permission_id IN ('warn','editAny','deleteAny')
                    AND user_group_id in ({$db->quote($whiteListedGroups)})
            "
            );
            $db->query(
                "INSERT IGNORE INTO xf_permission_entry (user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
                SELECT DISTINCT user_group_id, user_id, 'report_queue', 'viewReportUser', permission_value, permission_value_int
                FROM xf_permission_entry
                WHERE permission_group_id = 'general' AND  permission_id IN ('warn','editBasicProfile')
                    AND user_group_id in ({$db->quote($whiteListedGroups)})
            "
            );
            $applied = true;
        }
        if ($previousVersion < 1020200)
        {
            foreach ($globalReportPerms as $perm)
            {
                $db->query(
                    "INSERT IGNORE INTO xf_permission_entry (user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
                    SELECT DISTINCT user_group_id, user_id, convert(permission_group_id USING utf8), ?, permission_value, permission_value_int
                    FROM xf_permission_entry
                    WHERE permission_group_id = 'general' AND permission_id IN ('warn','editBasicProfile')
                        AND user_group_id in ({$db->quote($whiteListedGroups)})
                ", $perm
                );
            }
            foreach ($globalReportQueuePerms as $perm)
            {
                $db->query(
                    "INSERT IGNORE INTO xf_permission_entry (user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
                    SELECT DISTINCT user_group_id, user_id, 'report_queue', ?, permission_value, permission_value_int
                    FROM xf_permission_entry
                    WHERE permission_group_id = 'general' AND permission_id IN ('warn','editBasicProfile')
                        AND user_group_id in ({$db->quote($whiteListedGroups)})
                ", $perm
                );
            }
            $applied = true;
        }

        return $applied;
    }

    protected function getPermissionCombinationIdsToRebuild(): array
    {
        $permissionCombinationIds = array_values($this->db()->fetchAllColumn("
                SELECT combinationGroup.permission_combination_id
                FROM 
                (
                    SELECT entry.user_group_id
                    FROM xf_permission_entry AS entry
                    WHERE entry.user_group_id <> 0
                          AND entry.permission_value = 'allow'
                          AND ((entry.permission_group_id = 'report_queue') OR (entry.permission_group_id = 'general' AND entry.permission_id = 'viewReports'))
                    UNION 
                    SELECT entry.user_group_id
                    FROM xf_permission_entry_content AS entry
                    WHERE entry.user_group_id <> 0
                          AND entry.permission_value = 'content_allow'
                          AND ((entry.permission_group_id = 'report_queue') OR (entry.permission_group_id = 'general' AND entry.permission_id = 'viewReports'))
                ) entry
                JOIN xf_permission_combination AS combinationGroup ON FIND_IN_SET(entry.user_group_id, combinationGroup.user_group_list)
                UNION
                SELECT combinationGroup.permission_combination_id
                FROM xf_permission_entry AS entry
                JOIN xf_permission_combination AS combinationGroup ON combinationGroup.user_id = entry.user_id
                WHERE entry.user_id <> 0
                      AND entry.permission_value = 'allow'
                      AND ((entry.permission_group_id = 'report_queue') OR (entry.permission_group_id = 'general' AND entry.permission_id = 'viewReports'))
                UNION 
                SELECT combinationGroup.permission_combination_id
                FROM xf_permission_entry_content AS entry
                JOIN xf_permission_combination AS combinationGroup ON combinationGroup.user_id = entry.user_id
                WHERE entry.user_id <> 0
                      AND entry.permission_value = 'content_allow'
                      AND ((entry.permission_group_id = 'report_queue') OR (entry.permission_group_id = 'general' AND entry.permission_id = 'viewReports'))
            "));
        sort($permissionCombinationIds);

        return $permissionCombinationIds;
    }


    public function applySchemaNewTables(): void
    {
        $sm = $this->schemaManager();

        foreach ($this->getTables() as $tableName => $callback)
        {
            $sm->createTable($tableName, $callback);
            $sm->alterTable($tableName, $callback);
        }
    }

    public function applySchemaUpdates(): void
    {
        $sm = $this->schemaManager();

        foreach ($this->getAlterTables() as $tableName => $callback)
        {
            if ($sm->tableExists($tableName))
            {
                $sm->alterTable($tableName, $callback);
            }
        }
    }

    protected function getTables(): array
    {
        $tables = [];

        $tables['xf_sv_warning_log'] = function ($table) {
            /** @var Create|Alter $table */
            $this->addOrChangeColumn($table, 'warning_log_id', 'int')->autoIncrement();
            $this->addOrChangeColumn($table, 'warning_edit_date', 'int');
            $this->addOrChangeColumn($table, 'operation_type', 'enum')->values(WarningType::getAll());
            $this->addOrChangeColumn($table, 'warning_id', 'int')->nullable(true)->setDefault(null);
            $this->addOrChangeColumn($table, 'content_type', 'varbinary', 25);
            $this->addOrChangeColumn($table, 'content_id', 'int');
            $this->addOrChangeColumn($table, 'content_title', 'varchar', 255);
            $this->addOrChangeColumn($table, 'user_id', 'int');
            $this->addOrChangeColumn($table, 'warning_date', 'int');
            $this->addOrChangeColumn($table, 'warning_user_id', 'int');
            $this->addOrChangeColumn($table, 'warning_definition_id', 'int')->nullable(true)->setDefault(null);
            $this->addOrChangeColumn($table, 'title', 'varchar', 255);
            $this->addOrChangeColumn($table, 'notes', 'text');
            $this->addOrChangeColumn($table, 'points', 'smallint')->nullable(true)->setDefault(null);
            $this->addOrChangeColumn($table, 'expiry_date', 'int');
            $this->addOrChangeColumn($table, 'is_expired', 'tinyint', 3);
            $this->addOrChangeColumn($table, 'extra_user_group_ids', 'varbinary', 255);

            $this->addOrChangeColumn($table, 'reply_ban_node_id', 'int')->nullable(true)->setDefault(null);
            $this->addOrChangeColumn($table, 'reply_ban_thread_id', 'int')->nullable(true)->setDefault(null);
            $this->addOrChangeColumn($table, 'reply_ban_post_id', 'int')->nullable(true)->setDefault(null);
            $this->addOrChangeColumn($table, 'public_banner', 'varchar', 255)->nullable()->setDefault(null);
            $this->addOrChangeColumn($table, 'is_latest_version', 'tinyint')->setDefault(0);

            $table->addKey(['content_type', 'content_id','warning_edit_date']);
            $table->addKey(['reply_ban_thread_id', 'user_id', 'warning_edit_date']);
        };

        return $tables;
    }

    protected function getAlterTables(): array
    {
        $tables = [];

        $tables['xf_thread_reply_ban'] = function (Alter $table) {
            $this->addOrChangeColumn($table, 'post_id', 'int')->nullable(true)->setDefault(null);
        };

        $tables['xf_report'] = function (Alter $table) {
            $this->addOrChangeColumn($table, 'last_modified_id', 'int')->setDefault(0);
            $this->addOrChangeColumn($table, 'assigned_date', 'int')->nullable(true)->setDefault(null);
            $this->addOrChangeColumn($table, 'assigner_user_id', 'int')->nullable(true)->setDefault(null);
        };

        $tables['xf_report_comment'] = function (Alter $table) {
            $this->addOrChangeColumn($table, 'warning_log_id', 'int')->nullable(true)->setDefault(null);
            $this->addOrChangeColumn($table, 'reaction_score', 'int')->unsigned(false)->setDefault(0);
            $this->addOrChangeColumn($table, 'reactions', 'blob')->nullable()->setDefault(null);
            $this->addOrChangeColumn($table, 'reaction_users', 'blob')->nullable()->setDefault(null);
            $this->addOrChangeColumn($table, 'alertSent', 'tinyint', 3)->setDefault(0);
            $this->addOrChangeColumn($table, 'alertComment', 'MEDIUMTEXT')->nullable(true)->setDefault(null);
            $this->addOrChangeColumn($table, 'assigned_user_id', 'int')->nullable(true)->setDefault(null);
            $this->addOrChangeColumn($table, 'assigned_username', 'varchar', 50)->setDefault('');
            $this->addOrChangeColumn($table, 'attach_count', 'smallint', 5)->setDefault(0);
            $this->addOrChangeColumn($table, 'embed_metadata', 'blob')->nullable()->setDefault(null);
            $this->addOrChangeColumn($table, 'last_edit_date', 'int')->setDefault(0);
            $this->addOrChangeColumn($table, 'last_edit_user_id', 'int')->setDefault(0);
            $this->addOrChangeColumn($table, 'edit_count', 'int')->setDefault(0);
            $this->addOrChangeColumn($table, 'ip_id', 'bigint')->nullable()->setDefault(null);
            $table->addKey('warning_log_id', 'warning_log_id');
        };

        $tables['xf_permission_entry'] = function (Alter $table) {
            $table->addKey(['permission_group_id','permission_id']);
        };

        $tables['xf_permission_entry_content'] = function (Alter $table) {
            $table->addKey(['permission_group_id','permission_id']);
        };

        $tables['xf_user_option'] = function (Alter $table) {
            $this->addOrChangeColumn($table, 'sv_reportimprov_approval_filters', 'blob')->nullable(true)->setDefault(null);
        };

        return $tables;
    }

    protected function getRemoveAlterTables(): array
    {
        $tables = [];

        $tables['xf_thread_reply_ban'] = function (Alter $table) {
            $table->dropColumns(['post_id']);
        };

        $tables['xf_report'] = function (Alter $table) {
            $table->dropColumns(['last_modified_id']);
        };

        $tables['xf_report_comment'] = function (Alter $table) {
            $table->dropColumns([
                'warning_log_id',
                'reactions',
                'reaction_users',
                'alertSent',
                'alertComment',
                'attach_count',
                'embed_metadata',
                'last_edit_date',
                'last_edit_user_id',
                'edit_count',
            ]);
        };

        $tables['xf_user_option'] = function (Alter $table) {
            $table->dropColumns(['sv_reportimprov_approval_filters']);
        };

        return $tables;
    }
}
