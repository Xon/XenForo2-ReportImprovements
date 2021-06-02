<?php

namespace SV\ReportImprovements\XF\Service\Report;

use XF\Mvc\Entity\Entity;

/**
 * Extends \XF\Service\Report\Creator
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

        return parent::_save();
    }
}