<?php

namespace SV\ReportImprovements\Job\Upgrades;

use NF\Tickets\Entity\Message;
use SV\MultiPrefix\XF\Entity\Thread;
use SV\ReportImprovements\XF\Entity\Report as ExtendedReportEntity;
use SV\ReportImprovements\XF\Entity\ReportComment as ExtendedReportCommentEntity;
use SV\ReportImprovements\XF\Entity\Thread as ExtendedThreadEntity;
use SV\StandardLib\Helper;
use XF\Entity\Post as PostEntity;
use XF\Entity\Report as ReportEntity;
use XF\Finder\ReportComment as ReportCommentFinder;
use XF\Job\AbstractRebuildJob;
use function array_key_exists;

class EnrichReportPostInstall extends AbstractRebuildJob
{
    protected function getNextIds($start, $batch)
    {
        $db = \XF::db();

        return $db->fetchAllColumn($db->limit(
            '
            SELECT report_id
            FROM xf_report_comment 
            WHERE report_id > ?
            ORDER BY report_id
			', $batch
        ), $start);
    }

    protected function rebuildById($id)
    {
        /** @var ?ExtendedReportEntity $report */
        $report = Helper::find(ReportEntity::class, $id);
        if ($report === null)
        {
            return;
        }
        $db = \XF::db();

        if ($report->last_modified_id === 0)
        {
            $lastModified = $report->LastModified;
            if ($lastModified !== null)
            {
                $report->last_modified_id = $lastModified->report_comment_id;
            }
        }

        $content = $report->Content;
        $contentInfo = $report->content_info;
        $hasChanges = false;
        if ($content instanceof PostEntity)
        {
            if (!array_key_exists('post_date', $contentInfo))
            {
                $contentInfo['post_date'] = $content->post_date;
                $hasChanges = true;
            }
            if (!array_key_exists('prefix_id', $contentInfo))
            {
                /** @var ExtendedThreadEntity|Thread $thread */
                $thread = $content->Thread;
                if ($thread !== null)
                {
                    $contentInfo['prefix_id'] = $thread->sv_prefix_ids ?? $thread->prefix_id;
                    $hasChanges = true;
                }
            }
        }
        else if ($content instanceof Message)
        {
            if (!array_key_exists('message_date', $contentInfo))
            {
                $contentInfo['message_date'] = $content->message_date;
                $hasChanges = true;
            }
            if (!array_key_exists('ticket_status_id', $contentInfo))
            {
                $ticket = $content->Ticket;
                if ($ticket !== null)
                {
                    $contentInfo['ticket_status_id'] = $ticket->status_id;
                    $hasChanges = true;
                }
            }
        }
        if ($hasChanges)
        {
            $report->content_info = $contentInfo;
        }

        if ($report->assigned_user_id !== 0 && $report->assigned_date === null)
        {
            // Xenforo doesn't accurate track which report comment assigns (or unassigns) a report :(
            /** @var ?ExtendedReportCommentEntity $reportComment */
            $reportComment = Helper::finder(ReportCommentFinder::class)
                                       ->where('report_id', $report->report_id)
                                       ->where('state_change','assigned')
                                       ->order('comment_date', 'desc')
                                       ->fetchOne();
            if ($reportComment !== null)
            {
                $report->assigned_date = $reportComment->comment_date;
                $report->assigner_user_id = $reportComment->user_id;

                // attempt to link the last assigned user to the last report comment's data
                if ($reportComment->assigned_user_id === null)
                {
                    $reportComment->assigned_user_id = $reportComment->user_id;
                    if ($reportComment->assigned_username === '')
                    {
                        $reportComment->assigned_username = $db->fetchOne('SELECT username FROM xf_user WHERE user_id = ?', $reportComment->user_id) ?: '';
                    }
                }
                $reportComment->saveIfChanged();
            }
        }
        else if ($report->assigner_user_id === 0 && $report->assigned_date !== null)
        {
            $report->assigned_date = null;
        }

        if ($report->hasChanges())
        {
            $report->svDisableIndexing();
            $report->save();
        }
    }

    protected function getStatusType()
    {
        return \XF::phrase('report');
    }
}