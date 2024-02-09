<?php

namespace SV\ReportImprovements\XF\Service\Report;

use XF\Entity\Report;
use XF\Entity\Thread;
use XF\Mvc\Entity\Entity;

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
            $errors = \array_merge($errors, $this->report->getErrors());
        }

        return $errors;
    }

    protected function _save()
    {
        $db = $this->db();
        $db->beginTransaction();

        if ($this->threadCreator && (\XF::options()->svLogToReportCentreAndForum ?? false))
        {
            $threadCreator = $this->threadCreator;

            $thread = $threadCreator->save();
            \XF::asVisitor($this->user, function () use ($thread) {
                /** @var \XF\Repository\Thread $threadRepo */
                $threadRepo = $this->repository('XF:Thread');
                $threadRepo->markThreadReadByVisitor($thread, $thread->post_date);
            });

            $this->threadCreator = null;
        }

        /** @var Report|Thread $reportOrThread */
        $reportOrThread = parent::_save();

        if ($reportOrThread instanceof Report)
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