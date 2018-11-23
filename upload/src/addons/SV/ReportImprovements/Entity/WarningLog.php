<?php

namespace SV\ReportImprovements\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int warning_log_id
 * @property int warning_edit_date
 * @property string operation_type
 * @property int warning_id
 * @property string content_type
 * @property int content_id
 * @property string content_title
 * @property int user_id
 * @property int warning_date
 * @property int warning_user_id
 * @property int warning_definition_id
 * @property string title
 * @property string notes
 * @property int points
 * @property int expiry_date
 * @property int is_expired
 * @property string extra_user_group_ids
 * @property string sv_acknowledgement
 * @property int sv_acknowledgement_date
 * @property string sv_user_note
 * @property int reply_ban_thread_id
 * @property int reply_ban_post_id
 * @property int sv_suppress_notices
 *
 * RELATIONS
 * @property \XF\Entity\Warning Warning
 * @property \XF\Entity\User User
 */
class WarningLog extends Entity
{
    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_sv_warning_log';
        $structure->shortName = 'SV\ReportImprovements:WarningLog';
        $structure->primaryKey = 'warning_log_id';
        $structure->columns = [
            'warning_log_id'          => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
            'warning_edit_date'       => ['type' => self::UINT, 'required' => true],
            'operation_type'          => ['type' => self::STR, 'allowedValues' => ['new', 'edit', 'expire', 'delete', 'acknowledge'], 'required' => true],
            'warning_id'              => ['type' => self::UINT, 'required' => true],
            'content_type'            => ['type' => self::BINARY, 'maxLength' => 25, 'required' => true],
            'content_id'              => ['type' => self::UINT, 'required' => true],
            'content_title'           => ['type' => self::STR, 'maxLength' => 255, 'required' => true],
            'user_id'                 => ['type' => self::UINT, 'required' => true],
            'warning_date'            => ['type' => self::UINT, 'required' => true],
            'warning_user_id'         => ['type' => self::UINT, 'required' => true],
            'warning_definition_id'   => ['type' => self::UINT, 'required' => true],
            'title' => ['type' => self::STR, 'maxLength' => 255,
                'required' => 'please_enter_valid_title'
            ],
            'notes' => ['type' => self::STR, 'default' => ''],
            'points' => ['type' => self::UINT, 'max' => 65535, 'required' => true],
            'expiry_date' => ['type' => self::UINT, 'default' => 0],
            'is_expired' => ['type' => self::BOOL, 'default' => false],
            'extra_user_group_ids' => ['type' => self::LIST_COMMA, 'default' => [],
                'list' => ['type' => 'posint', 'unique' => true, 'sort' => SORT_NUMERIC]
            ],
            'sv_acknowledgement'      => ['type' => self::STR, 'allowedValues' => ['not_required', 'pending', 'completed'], 'default' => 'not_required'],
            'sv_acknowledgement_date' => ['type' => self::UINT, 'default' => 0],
            'sv_user_note'            => ['type' => self::STR, 'maxLength' => 10000, 'default' => ''],
            'reply_ban_thread_id'     => ['type' => self::UINT, 'default' => 0],
            'reply_ban_post_id'       => ['type' => self::UINT, 'default' => 0],
            'sv_suppress_notices'     => ['type' => self::UINT, 'maxLength' => 255, 'default' => 1],
        ];
        $structure->relations = [
            'Warning' => [
                'entity' => 'XF:Warning',
                'type' => self::TO_ONE,
                'conditions' => 'warning_id',
                'primary' => true
            ],
            'User' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true
            ]
        ];

        return $structure;
    }
}