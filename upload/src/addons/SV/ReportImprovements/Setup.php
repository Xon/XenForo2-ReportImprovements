<?php

namespace SV\ReportImprovements;

use SV\Utils\InstallerHelper;
use SV\Utils\InstallerSoftRequire;
use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;

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

    /**
     * Creates add-on tables.
     */
    public function installStep1()
    {
        $sm = $this->schemaManager();

        foreach ($this->getTables() as $tableName => $callback)
        {
            $sm->createTable($tableName, $callback);
            $sm->alterTable($tableName, $callback);
        }
    }

    /**
     * Alters core tables.
     */
    public function installStep2()
    {
        $sm = $this->schemaManager();

        foreach ($this->getAlterTables() as $tableName => $callback)
        {
            $sm->alterTable($tableName, $callback);
        }
    }

    public function installStep3()
    {
        $this->applyDefaultPermissions();
    }

    public function installStep4()
    {
        $this->upgrade1090100Step1();
    }

    public function installStep5()
    {
        $this->upgrade1090200Step1();
    }

    public function installStep6()
    {
        /** @noinspection SqlResolve */
        /** @noinspection SqlWithoutWhere */
        $this->db()->query('
          UPDATE xf_report
          SET last_modified_id = coalesce((SELECT report_comment_id 
                                  FROM xf_report_comment 
                                  WHERE xf_report_comment.report_id = xf_report.report_id
                                  ORDER BY comment_date DESC
                                  LIMIT 1), 0)
        ');
    }

    public function upgrade1090100Step1()
    {
        $this->app->jobManager()->enqueueUnique(
            'svRIUpgrade1090100Step1',
            'SV\ReportImprovements:Upgrades\Upgrade1090100Step1'
        );
    }

    public function upgrade1090200Step1()
    {
        $this->app->jobManager()->enqueueUnique(
            'svRIUpgrade1090200Step1',
            'SV\ReportImprovements:Upgrades\Upgrade1090200Step1'
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
        $this->installStep1();
    }

    public function upgrade2000002Step2()
    {
        $this->installStep2();
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
        $this->installStep6();
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

        $stepData = isset($stepParams[2]) ? $stepParams[2] : [];
        if (!isset($stepData['max']))
        {
            $stepData['max'] = $finder->total();
        }
        $alerts = $finder->limit(50)->fetch();
        if (!$alerts->count())
        {
            return null;
        }

        $next = 0;
        foreach ($alerts as $alert)
        {
            $next++;
            /** @var \XF\Entity\UserAlert $alert */
            $extraData = $alert->extra_data;
            /** @var \XF\Entity\ReportComment $comment */
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

    public function upgrade2030000Step1()
    {
        $this->installStep1();
    }

    public function upgrade2030000Step2()
    {
        $this->installStep2();
    }

    public function upgrade2040700Step1()
    {
        $this->migrateTableToReactions('xf_report_comment');
    }

    public function upgrade2040700Step2()
    {
        $this->renameLikeAlertOptionsToReactions('xf_report_comment');
    }

    public function upgrade2040700Step3()
    {
        $this->renameLikeAlertsToReactions('xf_report_comment');
    }

    public function upgrade2040700Step4()
    {
        $this->renameLikePermissionsToReactions([
            'general' => false // global only
        ], 'reportLike', 'reportReact');
    }

    public function upgrade2040700Step5()
    {
        $this->renameLikeStatsToReactions(['report', 'report_comment']);
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

    public function postInstall(array &$stateChanges)
    {
        \XF::app()->jobManager()->enqueueUnique(
            'warningLogMigration',
            'SV\ReportImprovements:WarningLogMigration',
            []
        );
    }

    public function postUpgrade($previousVersion, array &$stateChanges)
    {
        $this->applyDefaultPermissions($previousVersion);

        \XF::app()->jobManager()->enqueueUnique(
            'warningLogMigration',
            'SV\ReportImprovements:WarningLogMigration',
            []
        );
    }

    /**
     * @noinspection PhpDocMissingThrowsInspection
     * @param int|null $previousVersion
     * @return bool True if permissions were applied.
     */
    protected function applyDefaultPermissions($previousVersion = null)
    {
        $applied = false;
        $previousVersion = (int)$previousVersion;
        $db = $this->db();
        $globalReportPerms = ['assignReport', 'replyReport', 'replyReportClosed', 'updateReport', 'viewReporterUsername', 'viewReports', 'reportReact'];

        // content/global moderators before bulk update
        if (!$previousVersion || ($previousVersion <= 1040002) || ($previousVersion >= 2000000 && $previousVersion <= 2011000))
        {
            /** @var \XF\Repository\PermissionEntry $permissionEntryRepo */
            $permissionEntryRepo = \XF::repository('XF:PermissionEntry');
            /** @var \XF\Repository\Moderator $modRepo */
            $modRepo = \XF::repository('XF:Moderator');

            $contentModerators = $modRepo->findContentModeratorsForList()->fetch();
            /** @var \XF\Entity\ModeratorContent $contentModerator */
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
                if (isset($newPermissions['forum']['viewReportPost']) && $globalReportPerms)
                {
                    $globalPerms = $permissionEntryRepo->getGlobalUserPermissionEntries($user->user_id);
                    $newGlobalPerms = $globalPerms;
                    foreach ($globalReportPerms as $perm)
                    {
                        $newGlobalPerms['general'][$perm] = 'allow';
                    }

                    if ($newGlobalPerms != $globalPerms)
                    {
                        /** @var \XF\Service\UpdatePermissions $permissionUpdater */
                        $permissionUpdater = \XF::service('XF:UpdatePermissions');
                        $permissionUpdater->setUser($user);
                        $permissionUpdater->setGlobal();
                        $permissionUpdater->updatePermissions($newGlobalPerms);
                    }
                }
                if ($newPermissions != $permissions)
                {
                    /** @var \XF\Service\UpdatePermissions $permissionUpdater */
                    $permissionUpdater = \XF::service('XF:UpdatePermissions');
                    $permissionUpdater->setUser($user);
                    $permissionUpdater->setContent($contentModerator->content_type, $contentModerator->content_id);
                    $permissionUpdater->updatePermissions($newPermissions);
                }
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
                        'profilePost' => ['warn', 'editAnyPost', 'viewAny'],
                    ],
                    ['general' => ['viewReportProfilePost']]
                ],
                [
                    [
                        'conversation' => ['alwaysInvite', 'editAnyPost', 'viewAny'],
                    ],
                    ['general' => ['viewReportConversation']]
                ],
                [
                    [
                        'forum' => ['warn', 'editAnyPost', 'deleteAnyPost']
                    ],
                    ['forum' => ['viewReportPost']]
                ],
            ];

            $moderators = $modRepo->findModeratorsForList()->fetch();
            /** @var \XF\Entity\Moderator $moderator */
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
                foreach ($globalReportPermsChecks as $perm => $raw)
                {
                    list($checks, $assignments) = $raw;
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
                                                $newPermissions[$category][$newPerm] = "allow";
                                            }
                                        }
                                    }
                                    break 2;
                                }
                            }
                        }
                    }
                }

                if ($newPermissions != $permissions)
                {
                    /** @var \XF\Service\UpdatePermissions $permissionUpdater */
                    $permissionUpdater = \XF::service('XF:UpdatePermissions');
                    $permissionUpdater->setUser($moderator->User);
                    $permissionUpdater->setGlobal();
                    $permissionUpdater->updatePermissions($newPermissions);
                }
            }
        }

        if (!$previousVersion)
        {
            $db->query(
                "INSERT IGNORE INTO xf_permission_entry_content (content_type, content_id, user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
                SELECT DISTINCT content_type, content_id, user_group_id, user_id, convert(permission_group_id USING utf8), 'viewReportPost', permission_value, permission_value_int
                FROM xf_permission_entry_content
                WHERE permission_group_id = 'forum' AND permission_id IN ('warn','editAnyPost','deleteAnyPost')
            "
            );

            $db->query(
                "INSERT IGNORE INTO xf_permission_entry (user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
                SELECT DISTINCT user_group_id, user_id, convert(permission_group_id USING utf8), 'viewReportPost', permission_value, permission_value_int
                FROM xf_permission_entry
                WHERE permission_group_id = 'forum' AND permission_id IN ('warn','editAnyPost','deleteAnyPost')
            "
            );
            $db->query(
                "INSERT IGNORE INTO xf_permission_entry (user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
                SELECT DISTINCT user_group_id, user_id, convert(permission_group_id USING utf8), 'viewReportConversation', permission_value, permission_value_int
                FROM xf_permission_entry
                WHERE permission_group_id = 'conversation' AND permission_id IN ('alwaysInvite','editAnyPost','viewAny')
            "
            );
            $db->query(
                "INSERT IGNORE INTO xf_permission_entry (user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
                SELECT DISTINCT user_group_id, user_id, convert(permission_group_id USING utf8), 'viewReportProfilePost', permission_value, permission_value_int
                FROM xf_permission_entry
                WHERE permission_group_id = 'profilePost' AND permission_id IN ('warn','editAny','deleteAny')
            "
            );
            $db->query(
                "INSERT IGNORE INTO xf_permission_entry (user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
                SELECT DISTINCT user_group_id, user_id, convert(permission_group_id USING utf8), 'viewReportUser', permission_value, permission_value_int
                FROM xf_permission_entry
                WHERE permission_group_id = 'general' AND  permission_id IN ('warn','editBasicProfile')
            "
            );
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
                ", $perm
                );
            }
        }

        return $applied;
    }

    /**
     * @return array
     */
    protected function getTables()
    {
        $tables = [];

        $tables['xf_sv_warning_log'] = function ($table) {
            /** @var Create|Alter $table */
            $this->addOrChangeColumn($table, 'warning_log_id', 'int')->autoIncrement();
            $this->addOrChangeColumn($table, 'warning_edit_date', 'int');
            $this->addOrChangeColumn($table, 'operation_type', 'enum')->values(['new', 'edit', 'expire', 'delete', 'acknowledge']);
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

            $this->addOrChangeColumn($table, 'reply_ban_thread_id', 'int')->nullable(true)->setDefault(null);
            $this->addOrChangeColumn($table, 'reply_ban_post_id', 'int')->nullable(true)->setDefault(null);

            $table->addKey('warning_id');
            $table->addKey(['content_type', 'content_id'], 'content_type_id');
            $table->addKey(['user_id', 'warning_date'], 'user_id_date');
            $table->addKey(['expiry_date'], 'expiry');
            $table->addKey(['operation_type'], 'operation_type');
            $table->addKey(['warning_edit_date'], 'warning_edit_date');
        };

        return $tables;
    }

    /**
     * @return array
     */
    protected function getAlterTables()
    {
        $tables = [];

        $tables['xf_thread_reply_ban'] = function (Alter $table) {
            $this->addOrChangeColumn($table, 'post_id', 'int')->nullable(true)->setDefault(null);
        };

        $tables['xf_report'] = function (Alter $table) {
            $this->addOrChangeColumn($table, 'last_modified_id', 'int')->setDefault(0);
        };

        $tables['xf_report_comment'] = function (Alter $table) {
            $this->addOrChangeColumn($table, 'warning_log_id', 'int')->nullable(true)->setDefault(null);
            $table->addColumn('reactions', 'blob')->nullable();
            $table->addColumn('reaction_users', 'blob');
            $this->addOrChangeColumn($table, 'alertSent', 'tinyint', 3)->setDefault(0);
            $this->addOrChangeColumn($table, 'alertComment', 'MEDIUMTEXT')->nullable(true)->setDefault(null);
            $this->addOrChangeColumn($table, 'assigned_user_id', 'int')->nullable(true)->setDefault(null);
            $this->addOrChangeColumn($table, 'assigned_username', 'varchar', 50)->setDefault('');
            $table->addKey('warning_log_id', 'warning_log_id');
        };


        return $tables;
    }

    /**
     * @return array
     */
    protected function getRemoveAlterTables()
    {
        $tables = [];

        $tables['xf_thread_reply_ban'] = function (Alter $table) {
            $table->dropColumns(['post_id']);
        };

        $tables['xf_report'] = function (Alter $table) {
            $table->dropColumns(['last_modified_id']);
        };

        $tables['xf_report_comment'] = function (Alter $table) {
            $table->dropColumns(['warning_log_id', 'reactions', 'reaction_users', 'alertSent', 'alertComment']);
        };


        return $tables;
    }

    use InstallerSoftRequire;

    /**
     * @param array $errors
     * @param array $warnings
     */
    public function checkRequirements(&$errors = [], &$warnings = [])
    {
        $this->checkSoftRequires($errors, $warnings);
    }
}