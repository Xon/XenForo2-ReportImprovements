<?php

namespace SV\ReportImprovements\Job\Upgrades;

use SV\StandardLib\Helper;
use XF\Entity\ReportComment;
use XF\Job\AbstractRebuildJob;
use XF\Phrase;
use XF\Service\Report\CommentPreparer;

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
            WHERE (message LIKE \'%http:%\' OR message LIKE \'%https:%\') AND message NOT LIKE \'%[URL=%http%\' AND message NOT LIKE \'%[URL]http%\'
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
        /** @var ReportComment $comment */
        $comment = \SV\StandardLib\Helper::find(\XF\Entity\ReportComment::class, $id);
        if ($comment)
        {
            $user = $comment->User && $comment->User->user_id ? $comment->User : \XF::visitor();

            \XF::asVisitor($user, function () use ($comment) {

                $options = \XF::options();
                $urlToPageTitle = $options->urlToPageTitle['enabled'];
                $autoEmbedMedia = $options->autoEmbedMedia;
                $options->urlToPageTitle['enabled'] = false;
                $options->autoEmbedMedia['embedType'] = 0;
                try
                {
                    /** @var CommentPreparer $commentPreparer */
                    $commentPreparer = Helper::service(CommentPreparer::class, $comment);

                    $commentPreparer->setMessage($comment->message);

                    $comment->saveIfChanged($saved, false);
                }
                catch (\Exception $e)
                {
                    \XF::logException($e, true, "Error parsing report comment {$comment->report_comment_id} :", true);
                    throw $e;
                }
                finally
                {
                    $options->urlToPageTitle['enabled'] = $urlToPageTitle;
                    $options->autoEmbedMedia = $autoEmbedMedia;
                }
            });
        }
    }

    /**
     * @return Phrase
     */
    protected function getStatusType()
    {
        return \XF::phrase('report_comment');
    }
}