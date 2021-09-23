<?php

namespace SV\ReportImprovements\ModeratorLog;

use SV\ReportImprovements\XF\Entity\ReportComment as ReportCommentEntity;
use XF\Entity\ModeratorLog;
use XF\ModeratorLog\AbstractHandler;
use XF\Mvc\Entity\Entity;

/**
 * Class PageText
 *
 * @package SV\BbCodePages\ModeratorLog
 */
class ReportComment extends AbstractHandler
{
    public function isLoggableUser(\XF\Entity\User $actor)
    {
        return true;
    }

    /**
     * @param Entity $content
     * @param string $field
     * @param mixed $newValue
     * @param mixed $oldValue
     * @return bool|string
     */
    protected function getLogActionForChange(Entity $content, $field, $newValue, $oldValue)
    {
        switch ($field)
        {
            case 'message':
                return 'edit';
        }

        return false;
    }

    /**
     * @param ModeratorLog $log
     * @param Entity       $content
     */
    protected function setupLogEntityContent(ModeratorLog $log, Entity $content)
    {
        /** @var ReportCommentEntity $content */
        $report = $content->Report;

        $log->content_user_id = $content->user_id ?? 0;
        $log->content_username = $content->User ? $content->User->username : \XF::phrase('guest');
        $log->content_title = $report->title;
        $log->content_url = \XF::app()->router('public')->buildLink('nopath:reports/comment', $content);
        $log->discussion_content_type = 'report';
        $log->discussion_content_id = $report->report_id;
    }
}