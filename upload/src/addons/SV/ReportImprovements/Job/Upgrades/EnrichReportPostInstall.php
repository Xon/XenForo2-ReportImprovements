<?php

namespace SV\ReportImprovements\Job\Upgrades;

use SV\ReportImprovements\XF\Entity\Report;
use SV\ReportImprovements\XF\Entity\ReportComment;
use XF\Job\AbstractRebuildJob;

class EnrichReportPostInstall extends AbstractRebuildJob
{
    protected function getNextIds($start, $batch)
    {
        $db = $this->app->db();

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
        $report = $this->app->find('XF:Report', $id);
        if (!$report)
        {
            return;
        }
        assert($report instanceof Report);
        $db = $this->app->db();

        if ($report->last_modified_id === 0)
        {
            $lastModified = $report->LastModified;
            if ($lastModified !== null)
            {
                $report->last_modified_id = $lastModified->report_comment_id;
            }
        }

        if ($report->assigned_user_id !== 0 || $report->assigned_date === null)
        {
            // Xenforo doesn't accurate track which report comment assigns (or unassigns) a report :(
            $reportComment = \XF::app()->finder('XF:ReportComment')
                                       ->where('report_id', $report->report_id)
                                       ->where('state_change','assigned')
                                       ->order('comment_date', 'desc')
                                       ->fetchOne();
            if ($reportComment !== null)
            {
                assert($reportComment instanceof ReportComment);
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
        else
        {
            $report->assigned_date = null;
            $report->assigner_user_id = null;
        }

        $report->saveIfChanged();
    }

    protected function getStatusType()
    {
        return \XF::phrase('report_comment');
    }
}