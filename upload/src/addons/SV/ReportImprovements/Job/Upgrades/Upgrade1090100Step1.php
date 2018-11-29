<?php

namespace SV\ReportImprovements\Job\Upgrades;

use XF\Job\AbstractRebuildJob;

/**
 * Class Upgrade1090100Step1
 *
 * @package SV\ReportImprovements\Job\Upgrades
 */
class Upgrade1090100Step1 extends AbstractRebuildJob
{
    /**
     * @param $start
     * @param $batch
     * @return array
     */
    protected function getNextIds($start, $batch)
    {
        $db = \XF::db();

        return $db->fetchAllColumn($db->limit(
            '
            SELECT report_comment_id
            FROM xf_report_comment 
            WHERE message LIKE \'%@[%:%]%\' 
              AND report_comment_id > ?
            ORDER BY report_comment_id
			', $batch
        ), $start);
    }

    /**
     * @param $id
     */
    protected function rebuildById($id)
    {
        $db = \XF::db();

        $comment = $db->fetchRow(
            "
            SELECT report_comment_id, message
            FROM xf_report_comment 
            WHERE message LIKE '%@[%:%]%'
            AND report_comment_id = ?
        ", [$id]);
        if ($comment)
        {
            $output = preg_replace("/\@\[([^:]+):([^\]]+)\]/Uu", "[USER=$1]$2[/USER]", $comment['message']);
            if ($output !== null && $output != $comment['message'])
            {
                $db->query(
                    '
                    UPDATE xf_report_comment
                    SET message = ?
                    WHERE report_comment_id = ?
                ', [$output, $comment['report_comment_id']]
                );
            }
        }
    }

    /**
     * @return \XF\Phrase
     */
    protected function getStatusType()
    {
        return \XF::phrase('report_comment');
    }
}