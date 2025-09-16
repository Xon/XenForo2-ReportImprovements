<?php

namespace SV\ReportImprovements\XF\Service\Report;

use SV\StandardLib\Helper;
use XF\Entity\Report as ReportEntity;
use XF\Entity\Thread as ThreadEntity;
use XF\Mvc\Entity\Entity;
use XF\Repository\Thread as ThreadRepo;
use function array_merge;

/**
 * @extends \XF\Service\Report\Creator
 *
 * @property CommentPreparer             $commentPreparer
 */
class CreatorPatch extends XFCP_CreatorPatch
{
    public function createReport($contentType, Entity $content)
    {
        $options = \XF::options();
        $reportIntoForumId = $options->svLogToReportCentreAndForum ?? 0;
        if ($reportIntoForumId)
        {
            $options->offsetSet('reportIntoForumId', $reportIntoForumId);
        }
        parent::createReport($contentType, $content);
    }

    protected function _validate()
    {
        $errors = parent::_validate();

        if ($this->threadCreator && (\XF::options()->svLogToReportCentreAndForum ?? false))
        {
            $this->report->preSave();
            $errors = array_merge($errors, $this->report->getErrors());
        }

        return $errors;
    }

    protected function _save()
    {
        $db = \XF::db();
        $db->beginTransaction();

        if ($this->threadCreator && (\XF::options()->svLogToReportCentreAndForum ?? false))
        {
            $threadCreator = $this->threadCreator;

            $thread = $threadCreator->save();
            \XF::asVisitor($this->user, function () use ($thread) {
                $threadRepo = Helper::repository(ThreadRepo::class);
                $threadRepo->markThreadReadByVisitor($thread, $thread->post_date);
            });

            $this->threadCreator = null;
        }

        /** @var ReportEntity|ThreadEntity $reportOrThread */
        $reportOrThread = parent::_save();

        if ($reportOrThread instanceof ReportEntity)
        {
            $this->postSaveReport();
        }

        $db->commit();

        return $reportOrThread;
    }

    protected function postSaveReport()
    {
        // the report_count is updated via fast_update

        $this->commentPreparer->afterReportInsert();
    }
}