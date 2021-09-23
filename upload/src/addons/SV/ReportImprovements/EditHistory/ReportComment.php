<?php

namespace SV\ReportImprovements\EditHistory;

use SV\ReportImprovements\XF\Entity\ReportComment as ReportCommentEntity;
use XF\EditHistory\AbstractHandler;
use XF\Entity\EditHistory;
use XF\Mvc\Entity\Entity;
use XF\Phrase;

class ReportComment extends AbstractHandler
{
    public function canViewHistory(Entity $content) : bool
    {
        /** @var ReportCommentEntity $content */
        return $content->canViewHistory();
    }

    public function canRevertContent(Entity $content) : bool
    {
        /** @var ReportCommentEntity $content */
        return $content->canEdit();
    }

    public function getBreadcrumbs(Entity $content) : array
    {
        /** @var ReportCommentEntity $content */
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
        /** @var ReportCommentEntity $content */
        return $content->message;
    }

    public function revertToVersion(Entity $content, EditHistory $history, EditHistory $previous = null): bool
    {
        /** @var ReportCommentEntity $content */

        /** @var \SV\ReportImprovements\Service\Report\CommentEditor $editor */
        $editor = \XF::app()->service('SV\ReportImprovements:Report\CommentEditor', $content);

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

        return $editor->save();
    }

    /**
     * @param string $text
     * @param Entity|ReportCommentEntity|null $content
     * @return string
     */
    public function getHtmlFormattedContent($text, Entity $content = null) : string
    {
        return \XF::escapeString($text);
    }

    public function getEditCount(Entity $content) : int
    {
        /** @var ReportCommentEntity $content */
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
        /** @var ReportCommentEntity $content */

        return \XF::app()->router()->buildLink('reports/comment');
    }

    /**
     * Returns the content title for which is shown to user when viewing edit history.
     * @param Entity $content
     * @return string|Phrase|null
     * @noinspection PhpReturnDocTypeMismatchInspection
     */
    public function getContentTitle(Entity $content)
    {
        // not escaping this because XF allows HTML characters in page title
        /** @var ReportCommentEntity $content */

        return $content->Report->title;
    }
}