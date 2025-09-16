<?php

namespace SV\ReportImprovements\EditHistory;

use SV\ReportImprovements\Service\Report\CommentEditor;
use SV\ReportImprovements\XF\Entity\ReportComment as ExtendedReportCommentEntity;
use SV\StandardLib\Helper;
use XF\EditHistory\AbstractHandler;
use XF\Entity\EditHistory as EditHistoryEntity;
use XF\Mvc\Entity\Entity;
use XF\Phrase;

class ReportComment extends AbstractHandler
{
    public function canViewHistory(Entity $content) : bool
    {
        /** @var ExtendedReportCommentEntity $content */
        return $content->canViewHistory();
    }

    public function canRevertContent(Entity $content) : bool
    {
        /** @var ExtendedReportCommentEntity $content */
        return $content->canEdit();
    }

    public function getBreadcrumbs(Entity $content) : array
    {
        /** @var ExtendedReportCommentEntity $content */
        return $content->getBreadcrumbs();
    }

    public function getEntityWith() : array
    {
        $visitor = \XF::visitor();

        return [
            'Report',
            'Report.Permissions|' . $visitor->permission_combination_id,
        ];
    }

    public function getContentText(Entity $content) : string
    {
        /** @var ExtendedReportCommentEntity $content */
        return $content->message;
    }

    public function revertToVersion(Entity $content, EditHistoryEntity $history, ?EditHistoryEntity $previous = null)
    {
        /** @var ExtendedReportCommentEntity $content */

        /** @var CommentEditor $editor */
        $editor = Helper::service(CommentEditor::class, $content);

        $editor->setLogEdit(false);
        $editor->setMessage($history->old_text, false);

        if (!$previous || $previous->edit_user_id !== $content->user_id)
        {
            $content->last_edit_date = 0;
        }
        else
        {
            $content->last_edit_date = $previous->edit_date;
            $content->last_edit_user_id = $previous->edit_user_id;
        }

        $editor->save();

        return $content;
    }

    /**
     * @param string                                  $text
     * @param Entity|ExtendedReportCommentEntity|null $content
     * @return string
     */
    public function getHtmlFormattedContent($text, ?Entity $content = null) : string
    {
        return \XF::escapeString($text);
    }

    public function getEditCount(Entity $content) : int
    {
        /** @var ExtendedReportCommentEntity $content */
        return $content->edit_count;
    }

    /**
     * Returns the content URL to which user is redirected upon reverting edit history.
     * @param Entity $content
     *
     * @return string
     */
    public function getContentLink(Entity $content) : string
    {
        /** @var ExtendedReportCommentEntity $content */

        return \XF::app()->router()->buildLink('reports/comment');
    }

    /**
     * Returns the content title for which is shown to user when viewing edit history.
     *
     * @param Entity $content
     * @return string|Phrase|null
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function getContentTitle(Entity $content)
    {
        // not escaping this because XF allows HTML characters in page title
        /** @var ExtendedReportCommentEntity $content */

        return $content->Report->title;
    }
}