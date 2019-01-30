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
            $globalReportPerms = ['assignReport', 'replyReport', 'replyReportClosed', 'updateReport', 'viewReporterUsername', 'viewReports', 'reportLike'];
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

        if (!$previousVersion || $previousVersion <= 1040002)
        {
            /* todo - convert & support content moderators
            $moderatorModel = XenForo_Model::create('XenForo_Model_Moderator');
            $contentModerators = $moderatorModel->getContentModerators();
            foreach ($contentModerators as $contentModerator)
            {
                $permissions = @unserialize($contentModerator['moderator_permissions']);
                if (empty($permissions))
                {
                    continue;
                }
                $changes = false;
                if (!isset($permissions['forum']['viewReportPost']) &&
                    (!empty($permissions['forum']['editAnyPost']) || !empty($permissions['forum']['deleteAnyPost']) || !empty($permissions['forum']['warn']))
                )
                {
                    $permissions['forum']['viewReportPost'] = "1";
                    $changes = true;
                }
                if ($changes)
                {
                    $moderatorModel->insertOrUpdateContentModerator($contentModerator['user_id'], $contentModerator['content_type'], $contentModerator['content_id'], $permissions);
                }
            }
            $moderators = $moderatorModel->getAllGeneralModerators();
            $globalReportPerms = array(
                'assignReport'           => array('general' => array('warn', 'editBasicProfile')),
                'replyReport'            => array('general' => array('warn', 'editBasicProfile')),
                'replyReportClosed'      => array('general' => array('warn', 'editBasicProfile')),
                'updateReport'           => array('general' => array('warn', 'editBasicProfile')),
                'viewReporterUsername'   => array('general' => array('warn', 'editBasicProfile')),
                'viewReports'            => array('general' => array('warn', 'editBasicProfile')),
                'reportLike'             => array('general' => array('warn', 'editBasicProfile')),
                'viewReportPost'         => array('forum' => array('warn', 'editAnyPost', 'deleteAnyPost')),
                'viewReportConversation' => array('conversation' => array('alwaysInvite', 'editAnyPost', 'viewAny')),
                'viewReportProfilePost'  => array('profilePost' => array('warn', 'editAnyPost', 'viewAny')),
                'viewReportUser'         => array('general' => array('warn', 'editBasicProfile')),
            );

            foreach ($moderators as $moderator)
            {
                $userPerms = $db->fetchAll(
                    '
                    SELECT *
                    FROM xf_permission_entry
                    WHERE user_id = ?
                ', array($moderator['user_id'])
                );
                if (empty($userPerms))
                {
                    continue;
                }

                $userPermsGrouped = array();
                foreach ($userPerms as $userPerm)
                {
                    if ($userPerm['permission_value'] == 'allow')
                    {
                        $userPermsGrouped[$userPerm['permission_group_id']][$userPerm['permission_id']] = "1";
                    }
                }
                $permissions = @unserialize($moderator['moderator_permissions']);
                $changes = false;
                foreach ($globalReportPerms as $perm => $data)
                {
                    $keys = array_keys($data);
                    $category = reset($keys);
                    if (!isset($permissions[$category][$perm]) && !empty($data[$category]))
                    {
                        if (!empty($userPermsGrouped[$category][$perm]))
                        {
                            $permissions[$category][$perm] = "1";
                            $changes = true;
                            continue;
                        }
                        foreach ($data[$category] as $permToTest)
                        {
                            if (!empty($permissions[$category][$permToTest]) ||
                                !empty($userPermsGrouped[$category][$permToTest])
                            )
                            {
                                $permissions[$category][$perm] = "1";
                                $changes = true;
                                break;
                            }
                        }
                    }
                }
                if ($changes)
                {
                    $dw = XenForo_DataWriter::create('XenForo_DataWriter_Moderator');
                    $dw->setExistingData($moderator, true);
                    $dw->setGeneralPermissions($permissions);
                    $dw->save();
                }
            }
            */
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