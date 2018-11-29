<?php

namespace SV\ReportImprovements\Job\Upgrades;

use XF\Job\AbstractRebuildJob;

/**
 * Class Upgrade1090200Step1
 *
 * @package SV\ReportImprovements\Job\Upgrades
 */
class Upgrade1090200Step1 extends AbstractRebuildJob
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
            WHERE message LIKE \'%http%\' and message NOT LIKE \'%[URL=%http%\' and message NOT LIKE \'%[URL]http%\'
              AND report_comment_id > ?
            ORDER BY report_comment_id
			', $batch
        ), $start);
    }

    /**
     * @param $id
     * @throws \Exception
     */
    protected function rebuildById($id)
    {
        /** @var \XF\Entity\ReportComment $comment */
        $comment = \XF::app()->find('XF:ReportComment', $id);
        if ($comment)
        {
            $user = $comment->User && $comment->User->user_id ? $comment->User : \XF::visitor();

            \XF::asVisitor($user, function () use ($comment){
                /** @var \XF\Service\Report\CommentPreparer $commentPreparer */
                $commentPreparer = \XF::service('XF:Report\CommentPreparer', $comment);

                $commentPreparer->setMessage($comment->message);

                $comment->saveIfChanged($saved, false);
            });
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