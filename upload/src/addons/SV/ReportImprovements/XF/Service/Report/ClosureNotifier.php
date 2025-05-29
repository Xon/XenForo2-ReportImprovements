<?php

namespace SV\ReportImprovements\XF\Service\Report;

/**
 * @extends \XF\Service\Report\ClosureNotifier
 */
class ClosureNotifier extends XFCP_ClosureNotifier
{
    public function determineNotifiableUserIds()
    {
        if (\XF::$versionId < 2030570)
        {
            return parent::determineNotifiableUserIds();
        }

        // Backport XF2.3.4 fix for "Resolved report alert not sent if report assigned and unassigned"
        // https://xenforo.com/community/threads/resolved-report-alert-not-sent-if-report-assigned-and-unassigned.224971/

        $reportId = $this->report->report_id;
        $db = $this->db();

        $closeStates = ['resolved', 'rejected'];
        $lastCloseDate = $db->fetchOne(
            'SELECT comment_date
				FROM xf_report_comment
				WHERE report_id = ?
					AND state_change IN (' . $db->quote($closeStates) . ')
				ORDER BY comment_date DESC
				LIMIT 1 OFFSET 1',
            [$reportId]
        ) ?: 0;

        return $db->fetchAllColumn(
            'SELECT DISTINCT user_id
				FROM xf_report_comment
				WHERE report_id = ?
					AND comment_date > ?
					AND is_report = 1
					AND user_id <> 0',
            [$reportId, $lastCloseDate]
        );
    }
}