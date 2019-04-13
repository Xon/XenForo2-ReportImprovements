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
        $this->db()->query('
          update xf_report
          set last_modified_id = coalesce((select report_comment_id 
                                  from xf_report_comment 
                                  where xf_report_comment.report_id = xf_report.report_id
                                  order by comment_date desc
                                  limit 1), 0)
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
          update xf_report_comment
          set warning_log_id = null
          where warning_log_id = 0
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
          update xf_sv_warning_log
          set points = null, warning_definition_id = null
          where reply_ban_thread_id <> 0 and points = 0
        ');
    }

    public function upgrade2000002Step5()
    {
        /** @noinspection SqlResolve */
        $this->db()->query('
          update xf_sv_warning_log
          set reply_ban_thread_id = null
          where reply_ban_thread_id = 0
        ');
    }

    public function upgrade2000002Step6()
    {
        /** @noinspection SqlResolve */
        $this->db()->query('
          update xf_sv_warning_log
          set reply_ban_post_id = null
          where reply_ban_post_id = 0
        ');
    }

    public function upgrade2010400Step1()
    {
        $this->installStep6();
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
        $globalReportPerms = ['assignReport', 'replyReport', 'replyReportClosed', 'updateReport', 'viewReporterUsername', 'viewReports', 'reportLike'];

        // content/global moderators before bulk update
        if (!$previousVersion || ($previousVersion <= 1040002) || ($previousVersion >= 2000000 && $previousVersion <= 2011000))
        {
            /** @var \XF\Repository\PermissionEntry $permissionEntryRepo */
            $permissionEntryRepo = \XF::repository('XF:PermissionEntry');
            /** @var \XF\Repository\Moderator $modRepo */
            $modRepo = \XF::repository('XF:Moderator');

            $contentModerators = $modRepo->findContentModeratorsForList()->fetch();
            /** @var \XF\Entity\ModeratorContent $contentModerator */
            foreach($contentModerators as $contentModerator)
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
                        'general' => ['warn', 'editBasicProfile'],
                        'conversation' => ['alwaysInvite', 'editAnyPost', 'viewAny'],
                        'profilePost' => ['warn', 'editAnyPost', 'viewAny'],
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
            foreach($moderators as $moderator)
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
                    foreach($checks as $category => $permToTests)
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
                                        foreach($perms as $newPerm)
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
            $table->addKey(['content_type','content_id'], 'content_type_id');
            $table->addKey(['user_id','warning_date'], 'user_id_date');
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
            $this->addOrChangeColumn($table, 'likes', 'int')->setDefault(0);
            $this->addOrChangeColumn($table, 'like_users', 'BLOB')->nullable(true)->setDefault(null);
            $this->addOrChangeColumn($table, 'alertSent', 'tinyint', 3)->setDefault(0);
            $this->addOrChangeColumn($table, 'alertComment', 'MEDIUMTEXT')->nullable(true)->setDefault(null);
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
            $table->dropColumns(['warning_log_id', 'likes', 'like_users', 'alertSent', 'alertComment']);
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