<?php

namespace SV\ReportImprovements\Entity;

use SV\ReportImprovements\Enums\WarningType;
use SV\ReportImprovements\XF\Entity\ReportComment;
use SV\ReportImprovements\Finder\WarningLog as WarningLogFinder;
use SV\WarningImprovements\XF\Entity\WarningDefinition as ExtendedWarningDefinitionEntity;
use XF\Behavior\Indexable;
use XF\Entity\Post;
use XF\Entity\Thread;
use XF\Entity\ThreadReplyBan;
use XF\Entity\User;
use XF\Entity\Warning;
use XF\Entity\WarningDefinition;
use XF\Mvc\Entity\DeferredValue;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Phrase;
use function assert;

/**
 * COLUMNS
 *
 * @property ?int                    $warning_log_id
 * @property int                     $warning_edit_date
 * @property bool                    $is_latest_version
 * @property string                  $operation_type
 * @property ?int                    $warning_id
 * @property string                  $content_type
 * @property int                     $content_id
 * @property string                  $content_title
 * @property int                     $user_id
 * @property int                     $warning_date
 * @property int                     $warning_user_id
 * @property int                     $warning_definition_id
 * @property string                  $title
 * @property string                  $notes
 * @property int                     $points
 * @property int                     $expiry_date
 * @property int                     $is_expired
 * @property string                  $extra_user_group_ids
 * @property ?int                    $reply_ban_thread_id
 * @property ?int                    $reply_ban_post_id
 * @property ?string                 $public_banner_
 * GETTERS
 * @property-read ?Phrase            $definition_title
 * @property-read ?string            $public_banner
 * @property-read ?ThreadReplyBan    $ReplyBan
 * @property-read ?WarningDefinition $Definition
 * RELATIONS
 * @property-read ?ThreadReplyBan    $ReplyBan_
 * @property-read ?Warning           $Warning
 * @property-read ?WarningDefinition $Definition_
 * @property-read ?User              $WarnedBy
 * @property-read ?User              $User
 * @property-read ?Thread            $ReplyBanThread
 * @property-read ?Post              $ReplyBanPost
 * @property-read ?ReportComment     $ReportComment
 */
class WarningLog extends Entity
{
    use WarningInfoTrait;

    public function canView(): bool
    {
        if ($this->ReportComment === null)
        {
            return false;
        }

        return $this->ReportComment->canView();
    }

    protected function getContentTypeForOperationType(): ?Phrase
    {
        if ($this->warning_id)
        {
            return \XF::phrase('svReportImprov_operation_type_action.warning');
        }
        else if ($this->reply_ban_post_id)
        {
            return \XF::phrase('svReportImprov_operation_type_action.reply_ban_from_post');
        }
        else if ($this->reply_ban_thread_id)
        {
            return \XF::phrase('svReportImprov_operation_type_action.reply_ban');
        }

        return null;
    }

    public function getOperationTypePhrase(): Phrase
    {
        return \XF::phrase('svReportImprov_operation_type.' . $this->operation_type, [
            'contentType' => $this->getContentTypeForOperationType() ?? '',
        ]);
    }

    public function getReplyBan(): ? ThreadReplyBan
    {
        if (\array_key_exists('ReplyBan', $this->_relations))
        {
            return $this->ReplyBan_;
        }

        if (!$this->reply_ban_thread_id || !$this->ReplyBanThread)
        {
            return null;
        }

        return $this->ReplyBanThread->ReplyBans[$this->user_id];
    }

    public function getReplyBanLink():? string
    {
        $router = $this->app()->router('public');

        if ($this->reply_ban_post_id && $this->ReplyBanPost)
        {
            if ($thread = $this->ReplyBanPost->Thread)
            {
                $page = floor($this->ReplyBanPost->position / \XF::options()->messagesPerPage) + 1;

                return $router->buildLink('canonical:threads', $thread, ['page' => $page]) . '#post-' . $this->reply_ban_post_id;
            }

            return $router->buildLink('canonical:threads', $this->ReplyBanPost);
        }

        if ($this->reply_ban_thread_id && $this->ReplyBanThread)
        {
            return $router->buildLink('canonical:threads', $this->ReplyBanThread);
        }

        return null;
    }

    public function getDeferredPrimaryId(): DeferredValue
    {
        return $this->_getDeferredValue(
            function () {
                return $this->warning_log_id;
            }, 'save'
        );
    }

    public function isUserOperation(): bool
    {
        return $this->operation_type == WarningType::Acknowledge;
    }

    protected function _preSave()
    {
        parent::_preSave();

        if ($this->public_banner_ === '')
        {
            $this->public_banner = null;
        }
    }

    public function rebuildLatestVersionFlag(bool $doIndexUpdate = true): void
    {
        $db = $this->db();
        $finder = $this->finder('SV\ReportImprovements:WarningLog');
        assert($finder instanceof WarningLogFinder);

        $latestWarningLogId = 0;
        if ($this->warning_id !== null)
        {
            $latestWarningLogId = (int)$db->fetchOne('
                SELECT warning_log_id
                FROM xf_sv_warning_log
                WHERE content_type = ? AND content_id = ? and warning_id is not null
                ORDER BY warning_edit_date DESC, warning_log_id DESC 
                LIMIT 1
            ', [$this->content_type, $this->content_id]);
            $finder->where('content_type', $this->content_type)
                   ->where('content_id', $this->content_id);
        }
        else if ($this->reply_ban_thread_id !== null)
        {
            $latestWarningLogId = (int)$db->fetchOne('
                SELECT warning_log_id
                FROM xf_sv_warning_log
                WHERE reply_ban_thread_id = ? AND user_id = ?
                ORDER BY warning_edit_date DESC, warning_log_id DESC  
                LIMIT 1
            ', [$this->reply_ban_thread_id, $this->user_id]);
            $finder->where('reply_ban_thread_id', $this->reply_ban_thread_id)
                   ->where('user_id', $this->user_id);
        }

        $isLatestVersion = $this->is_latest_version;
        if ($latestWarningLogId === 0)
        {
            if (!$isLatestVersion)
            {
                $this->fastUpdate('is_latest_version', true);
            }
        }
        else if ($latestWarningLogId === $this->warning_log_id)
        {
            // while only 1 should be the latest, but patching any out-of-sync records is simple
            $warningLogs = $finder->where('is_latest_version', true)->fetch();
            foreach ($warningLogs as $warningLog)
            {
                assert($warningLog instanceof WarningLog);
                // use fastUpdate() otherwise save() will trigger a loop
                $warningLog->fastUpdate('is_latest_version', false);
                $this->triggerReindex();
            }
            if (!$isLatestVersion)
            {
                $this->fastUpdate('is_latest_version', true);
            }
        }
        else if ($isLatestVersion)
        {
            $this->fastUpdate('is_latest_version', false);
        }

        if ($doIndexUpdate && $isLatestVersion !== $this->is_latest_version)
        {
            $this->triggerReindex();
        }
    }

    protected function triggerReindex(): void
    {
        $indexable = $this->getBehavior('XF:Indexable');
        if ($indexable !== null)
        {
            assert($indexable instanceof Indexable);
            $indexable->triggerReindex();
        }
    }

    protected function _postSave()
    {
        parent::_postSave();

        if ($this->isInsert())
        {
            $this->rebuildLatestVersionFlag(false);
        }
        else if ($this->isChanged('is_latest_version'))
        {
            $this->rebuildLatestVersionFlag(true);
        }
    }

    /**
     * @param Structure $structure
     * @return Structure
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_sv_warning_log';
        $structure->shortName = 'SV\ReportImprovements:WarningLog';
        $structure->primaryKey = 'warning_log_id';
        $structure->contentType = 'warning_log';
        $structure->columns = [
            'warning_log_id'        => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
            'warning_edit_date'     => ['type' => self::UINT, 'required' => true, 'default' => \XF::$time],
            'is_latest_version'     => ['type' => self::BOOL, 'default' => false],
            'operation_type'        => ['type' => self::STR, 'allowedValues' => WarningType::get(), 'required' => true],
            'warning_id'            => ['type' => self::UINT, 'default' => null, 'nullable' => true],
            'content_type'          => ['type' => self::BINARY, 'maxLength' => 25, 'required' => true],
            'content_id'            => ['type' => self::UINT, 'required' => true],
            'content_title'         => ['type' => self::STR, 'maxLength' => 255, 'default' => ''],
            'public_banner'         => ['type' => self::STR, 'maxLength' => 255, 'nullable' => true, 'default' => null],
            'user_id'               => ['type' => self::UINT, 'required' => true],
            'warning_date'          => ['type' => self::UINT, 'required' => true],
            'warning_user_id'       => ['type' => self::UINT, 'required' => true],
            'warning_definition_id' => ['type' => self::UINT, 'default' => null, 'nullable' => true],
            'title'                 => ['type' => self::STR, 'maxLength' => 255, 'default' => '', 'noIndex' => true], // should be required but...
            'notes'                 => ['type' => self::STR, 'default' => ''],
            'points'                => ['type' => self::UINT, 'max' => 65535, 'nullable' => true, 'default' => null],
            'expiry_date'           => ['type' => self::UINT, 'default' => 0],
            'is_expired'            => ['type' => self::BOOL, 'default' => false],
            'extra_user_group_ids'  => [
                'type' => self::LIST_COMMA, 'default' => [],
                'list' => ['type' => 'posint', 'unique' => true, 'sort' => SORT_NUMERIC],
            ],
            'reply_ban_thread_id'   => ['type' => self::UINT, 'default' => null, 'nullable' => true],
            'reply_ban_post_id'     => ['type' => self::UINT, 'default' => null, 'nullable' => true],
        ];
        $structure->relations = [
            'ReportComment' => [
                'entity'     => 'XF:ReportComment',
                'type'       => self::TO_ONE,
                'conditions' => 'warning_log_id',
                'primary'    => true,
            ],
            'Warning'        => [
                'entity'     => 'XF:Warning',
                'type'       => self::TO_ONE,
                'conditions' => 'warning_id',
                'primary'    => true,
            ],
            'User'           => [
                'entity'     => 'XF:User',
                'type'       => self::TO_ONE,
                'conditions' => 'user_id',
                'primary'    => true,
            ],
            'WarnedBy' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => [['user_id', '=', '$warning_user_id']],
                'primary' => true
            ],
            'Definition' => [
                'entity' => 'XF:WarningDefinition',
                'type' => self::TO_ONE,
                'conditions' => 'warning_definition_id',
                'primary' => true
            ],
            'ReplyBan'       => [
                'entity'     => 'XF:ThreadReplyBan',
                'type'       => self::TO_ONE,
                'conditions' => [
                    ['thread_id', '=', '$reply_ban_thread_id'],
                    ['user_id', '=', '$user_id'],
                ],
                'primary'    => true,
            ],
            'ReplyBanThread' => [
                'entity'     => 'XF:Thread',
                'type'       => self::TO_ONE,
                'conditions' => [
                    ['thread_id', '=', '$reply_ban_thread_id'],
                ],
                'primary'    => true,
            ],
            'ReplyBanPost'   => [
                'entity'     => 'XF:Post',
                'type'       => self::TO_ONE,
                'conditions' => [
                    ['post_id', '=', '$reply_ban_post_id'],
                ],
                'primary'    => true,
            ],
        ];
        $structure->defaultWith[] = 'Warning';
        $structure->getters = [
            'OperationTypePhrase' => ['getter' => 'getOperationTypePhrase', 'cache' => false],
            'ReplyBan'            => ['getter' => 'getReplyBan','cache' => true],
            'ReplyBanLink'        => ['getter' => 'getReplyBanLink','cache' => true],
            'definition_title'    => ['getter' => 'getDefinitionTitle', 'cache' => true],
        ];
        // currently deliberately empty checkForUpdates, as this is indexed when a linked ReportComment is created
        // except for the resyncLatestVersionFlag function which explicitly triggers re-indexing
        $structure->behaviors['XF:Indexable'] = [
            'checkForUpdates' => [],
        ];

        return $structure;
    }
}