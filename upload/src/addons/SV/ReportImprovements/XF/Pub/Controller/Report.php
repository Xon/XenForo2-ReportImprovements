<?php

namespace SV\ReportImprovements\XF\Pub\Controller;

use SV\ReportImprovements\Repository\ReportQueue as ReportQueueRepo;
use SV\ReportImprovements\Service\Report\CommentEditor;
use SV\ReportImprovements\XF\Entity\ReportComment as ExtendedReportCommentEntity;
use SV\ReportImprovements\XF\Entity\Report as ExtendedReportEntity;
use SV\ReportImprovements\XF\Entity\User as ExtendedUserEntity;
use SV\ReportImprovements\XF\Service\Report\Commenter as ExtendedReportCommenterService;
use SV\StandardLib\Helper;
use XF\ControllerPlugin\BbCodePreview as BbCodePreviewPlugin;
use XF\ControllerPlugin\Editor as EditorPlugin;
use XF\ControllerPlugin\Ip as IpPlugin;
use XF\ControllerPlugin\Reaction as ReactionControllerPlugin;
use XF\Entity\ConversationMessage;
use XF\Entity\ConversationRecipient;
use XF\Entity\ReportComment;
use XF\Entity\ReportComment as ReportCommentEntity;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception as ReplyException;
use XF\Mvc\Reply\View as ViewReply;
use XF\Repository\Attachment as AttachmentRepo;
use XF\Repository\Report as ReportRepo;
use XF\Repository\Unfurl as UnfurlRepo;
use XF\Service\Conversation\Inviter as InviterService;
use XF\Service\Report\Commenter as ReportCommenterService;

/**
 * @extends \XF\Pub\Controller\Report
 */
class Report extends XFCP_Report
{
    /**
     * @param string       $action
     * @param ParameterBag $params
     * @throws ReplyException
     */
    protected function preDispatchController($action, ParameterBag $params)
    {
        /** @var ExtendedUserEntity $visitor */
        $visitor = \XF::visitor();
        if (!$visitor->canViewReports($error))
        {
            throw $this->exception($this->noPermission($error));
        }

        $is_moderator = $visitor->is_moderator;
        $wasReadonly = $visitor->getReadOnly();
        if (!$is_moderator)
        {
            $visitor->setReadOnly(false);
            $visitor->is_moderator = true;
            $visitor->setReadOnly(true);
        }
        try
        {
            parent::preDispatchController($action, $params);
        }
        finally
        {
            if (!$is_moderator)
            {
                $visitor->setReadOnly(false);
                $visitor->is_moderator = false;
                if ($wasReadonly)
                {
                    $visitor->setReadOnly($wasReadonly);
                }
            }
        }
    }

    public function actionIndex(ParameterBag $params)
    {
        // avoid N+1 look up behaviour, just cache all node perms
        \XF::visitor()->cacheNodePermissions();

        $reply = parent::actionIndex($params);

        if ($reply instanceof ViewReply)
        {
            $reports = [];
            /** @var \XF\Entity\Report $report */
            $openReports = $reply->getParam('openReports');
            if ($openReports instanceof AbstractCollection)
            {
                $openReports = $openReports->toArray();
            }
            if (\is_array($openReports))
            {
                $reports = $reports + $openReports;
            }
            $closedReports = $reply->getParam('closedReports');
            if ($closedReports instanceof AbstractCollection)
            {
                $closedReports = $closedReports->toArray();
            }
            if (\is_array($closedReports))
            {
                $reports = $reports + $closedReports;
            }

            /** @var \SV\ReportImprovements\XF\Repository\Report $reportRepo */
            $reportRepo = Helper::repository(ReportRepo::class);
            $reportRepo->filterViewableReports(new ArrayCollection($reports));
        }

        return $reply;
    }

    public function actionView(ParameterBag $params)
    {
        $reply = parent::actionView($params);

        if ($reply instanceof ViewReply &&
            ($report = $reply->getParam('report')) &&
            ($comments = $reply->getParam('comments')))
        {
            /** @var ExtendedReportEntity $report */
            /** @var AbstractCollection|array $comments */

            $reportQueueRepo = Helper::repository(ReportQueueRepo::class);
            $reportQueueRepo->addReplyBansToComments($comments);

            $attachmentRepo = Helper::repository(AttachmentRepo::class);
            $attachmentRepo->addAttachmentsToContent($comments, 'report_comment');

            $unfurlRepo = Helper::repository(UnfurlRepo::class);
            $unfurlRepo->addUnfurlsToContent($comments, $this->isRobot());

            $reply->setParam('attachmentData', $this->getReplyAttachmentData($report));
        }

        return $reply;
    }

    public function actionComment(ParameterBag $params)
    {
        $reportCommentId = $this->filter('report_comment_id', 'uint');
        $reportCommentId = $reportCommentId ?: (int)$params->get('report_comment_id'); // support older style links

        $reportComment = $this->assertViewableReportComment($reportCommentId);
        /** @var ExtendedReportEntity $report */
        $reportId = (int)$params->get('report_id');
        $report = $reportId ? $this->assertViewableReport($reportId) : $reportComment->Report;

        if ($reportComment->report_id !== $report->report_id)
        {
            return $this->redirect($this->buildLink('canonical:reports/comment', $reportComment));
        }

        return $this->redirect($this->buildLink('canonical:reports', $reportComment->Report) . '#report-comment-' . $reportComment->report_comment_id);
    }

    public function actionCommentIp(ParameterBag $params)
    {
        $reportComment = $this->assertViewableReportComment((int)$params->get('report_comment_id'));
        $breadcrumbs = $reportComment->getBreadcrumbs();

        $ipPlugin = Helper::plugin($this, IpPlugin::class);
        return $ipPlugin->actionIp($reportComment, $breadcrumbs);
    }

    public function actionCommentEdit(ParameterBag $params)
    {
        $reportComment = $this->assertViewableReportComment((int)$params->get('report_comment_id'));
        $report = $reportComment->Report;
        if (!$reportComment->canEdit($error))
        {
            return $this->noPermission($error);
        }

        if ($this->isPost())
        {
            $editor = $this->setupReportCommentEdit($reportComment);
            if (!$editor->validate($errors))
            {
                return $this->error($errors);
            }
            $editor->save();

            if ($this->filter('_xfWithData', 'bool') && $this->filter('_xfInlineEdit', 'bool'))
            {
                $attachmentRepo = Helper::repository(AttachmentRepo::class);
                $attachmentRepo->addAttachmentsToContent([
                    $reportComment->report_comment_id => $reportComment
                ], 'conversation_message');

                $viewParams = [
                    'report'  => $report,
                    'comment' => $reportComment
                ];
                $reply = $this->view('XF:Report\Comment\EditNewMessage', 'svReportImprovements_report_comment_edit_new_message', $viewParams);
                $reply->setJsonParam('message', \XF::phrase('your_changes_have_been_saved'));

                return $reply;
            }
            else
            {
                return $this->redirect($this->buildLink('canonical:reports/comment', $reportComment));
            }
        }
        else
        {
            if ($reportComment->Report->canUploadAndManageAttachments())
            {
                $attachmentRepo = Helper::repository(AttachmentRepo::class);
                $attachmentData = $attachmentRepo->getEditorData('report_comment', $reportComment);
            }
            else
            {
                $attachmentData = null;
            }

            $viewParams = [
                'report'  => $report,
                'comment' => $reportComment,

                'attachmentData' => $attachmentData,
                'quickEdit'      => $this->filter('_xfWithData', 'bool'),
            ];

            return $this->view('XF:Report\Comment\Edit', 'svReportImprovements_report_comment_edit', $viewParams);
        }
    }

    public function actionCommentHistory(ParameterBag $params)
    {
        $reportComment = $this->assertViewableReportComment((int)$params->get('report_comment_id'));

        return $this->rerouteController('XF:EditHistory', 'index', [
            'content_type' => 'report_comment',
            'content_id' => $reportComment->report_comment_id
        ]);
    }

    protected function getReplyAttachmentData(ExtendedReportEntity $report, $forceAttachmentHash = null)
    {
        if ($report->canUploadAndManageAttachments())
        {
            if ($forceAttachmentHash !== null)
            {
                $attachmentHash = $forceAttachmentHash;
            }
            else
            {
                /** @noinspection PhpUndefinedFieldInspection */
                $attachmentHash = $report->draft_comment->attachment_hash;
            }

            $attachmentRepo = Helper::repository(AttachmentRepo::class);
            return $attachmentRepo->getEditorData('report_comment', $report, $attachmentHash);
        }

        return null;
    }

    /**
     * @param ReportComment|ExtendedReportCommentEntity $reportComment
     * @return CommentEditor
     */
    protected function setupReportCommentEdit(ReportComment $reportComment)
    {
        /** @var EditorPlugin $editorPlugin */
        $editorPlugin = Helper::plugin($this, EditorPlugin::class);
        $message = $editorPlugin->fromInput('message');

        /** @var CommentEditor $editor */
        $editor = Helper::service(CommentEditor::class, $reportComment);
        $editor->setMessage($message);

        $report = $reportComment->Report;

        if ($report->canUploadAndManageAttachments())
        {
            $editor->setAttachmentHash($this->filter('attachment_hash', 'str'));
        }

        return $editor;
    }

    /**
     * @param \XF\Entity\Report|ExtendedReportEntity $report
     * @return ReportCommenterService
     * @throws ReplyException
     */
    protected function setupReportComment(\XF\Entity\Report $report)
    {
        if (!$report->canUpdate($error))
        {
            // pls no funny business kthxbai :smile:
            if ($this->request()->exists('report_state')
                || $this->request()->exists('send_alert')
                || $this->request()->exists('alert_comment')
            )
            {
                throw $this->exception($this->noPermission($error));
            }
        }

        $selfAssignUnassign = $this->filter('self_assign_unassign', 'bool');
        if ($selfAssignUnassign)
        {
            /** @var ExtendedUserEntity $visitor */
            $visitor = \XF::visitor();
            $reportState = 'assigned';

            if ($report->report_state === 'assigned')
            {
                if ($report->assigned_user_id !== $visitor->user_id && !$report->canAssign($error))
                {
                    throw $this->exception($this->noPermission($error));
                }
                $reportState = 'open';
            }

            $this->request()->set('report_state', $reportState);
        }

        if (
            !$report->canComment($error)
            && ($this->request()->exists('message') || $this->request()->exists('message_html'))
        )
        {
            throw $this->exception($this->noPermission($error));
        }

        if (!$selfAssignUnassign && !$report->canComment() && !$report->canUpdate())
        {
            throw $this->exception(
                $this->error(\XF::phrase('svReportImprov_please_assign_or_unassign_the_report_item'))
            );
        }

        /** @var ExtendedReportCommenterService $editor */
        $editor = parent::setupReportComment($report);

        $editor->getComment()->setOption('log_moderator', 'true');
        $editor->logIp(true);
        if ($report->canUploadAndManageAttachments())
        {
            $editor->setAttachmentHash($this->filter('attachment_hash', 'str'));
        }

        return $editor;
    }

    /**
     * @param ParameterBag $params
     * @return AbstractReply
     * @throws ReplyException
     */
    public function actionReassign(ParameterBag $params)
    {
        $this->assertPostOnly();

        /** @var ExtendedReportEntity $report */
        /** @noinspection PhpUndefinedFieldInspection */
        $report = $this->assertViewableReport($params->report_id);
        if (!$report->canAssign($error))
        {
            return $this->noPermission($error);
        }

        return parent::actionReassign($params);
    }

    /**
     * @param ParameterBag $params
     * @return AbstractReply
     * @throws ReplyException
     */
    public function actionCommentReact(ParameterBag $params)
    {
        $reportComment = $this->assertViewableReportComment((int)$params->get('report_comment_id'));
        if (!$reportComment->canReact($error))
        {
            return $this->noPermission($error);
        }

        $reactionLinkParams = [];

        /** @var ReactionControllerPlugin $reactionControllerPlugin */
        $reactionControllerPlugin = Helper::plugin($this, ReactionControllerPlugin::class);
        return $reactionControllerPlugin->actionReact(
            $reportComment,
            $this->buildLink('reports/comment', $reportComment),
            $this->buildLink('reports/comment/react', $reportComment, $reactionLinkParams),
            $this->buildLink('reports/comment/reactions', $reportComment, $reactionLinkParams)
        );
    }

    /**
     * @param ParameterBag $params
     * @return AbstractReply
     * @throws ReplyException
     */
    public function actionCommentReactions(ParameterBag $params)
    {
        $reportComment = $this->assertViewableReportComment((int)$params->get('report_comment_id'));

        $breadcrumbs = $reportComment->getBreadcrumbs();
        $title = \XF::phrase('sv_members_who_reacted_this_report_comment');

        /** @var ReactionControllerPlugin $reactionControllerPlugin */
        $reactionControllerPlugin = Helper::plugin($this, ReactionControllerPlugin::class);
        return $reactionControllerPlugin->actionReactions(
            $reportComment,
            'reports/comment/reactions',
            $title,
            $breadcrumbs,
            []
        );
    }

    public function actionConversationJoin(ParameterBag $params)
    {
        /** @noinspection PhpUndefinedFieldInspection */
        /** @var ExtendedReportEntity $report */
        $report = $this->assertViewableReport($params->report_id);

        if (!$report->canJoinConversation())
        {
            return $this->notFound();
        }

        /** @var ConversationMessage $conversationMessage */
        $conversationMessage = $report->Content;
        if (!$conversationMessage || !$conversationMessage->Conversation)
        {
            return $this->notFound();
        }

        if ($this->isPost())
        {
            $visitor = \XF::visitor();
            /** @var ConversationRecipient|null $existingRecipient */
            $existingRecipient = $conversationMessage->Conversation->Recipients[$visitor->user_id] ?? null;
            if ($existingRecipient)
            {
                $existingRecipient->recipient_state = 'active';
                $existingRecipient->saveIfChanged();
            }
            else
            {
                $service = Helper::service(InviterService::class, $conversationMessage->Conversation, $conversationMessage->Conversation->Starter);
                $service->setAutoSendNotifications(false);
                $service->setRecipientsTrusted($visitor);
                $service->save();
            }

            return $this->redirect(\XF::app()->router()->buildLink('conversations/messages', $conversationMessage));
        }

        return $this->view('XF:Report\XenForo_ViewPublic_Report_ConversationJoin', 'svReportImprov_conversation_join', [
            'report'       => $report,
            'conversation' => $conversationMessage->Conversation,
        ]);
    }

    public function actionPreview(ParameterBag $params):AbstractReply
    {
        $this->assertPostOnly();

        /** @var ExtendedReportEntity $report */
        /** @noinspection PhpUndefinedFieldInspection */
        $report = $this->assertViewableReport($params->report_id);
        if (!$report->canComment())
        {
            return $this->noPermission();
        }

        $commenter = $this->setupReportComment($report);
        if (!$commenter->validate($errors))
        {
            return $this->error($errors);
        }
        /** @var ExtendedReportCommentEntity $reportComment */
        $reportComment = $commenter->getComment();

        $attachments = [];
        $tempHash = $this->filter('attachment_hash', 'str');

        if ($report->canUploadAndManageAttachments())
        {
            $attachmentRepo = Helper::repository(AttachmentRepo::class);
            $attachmentData = $attachmentRepo->getEditorData('report_comment', $reportComment, $tempHash);
            $attachments = $attachmentData['attachments'];
        }

        /** @var BbCodePreviewPlugin $bbCodePreview */
        $bbCodePreview = Helper::plugin($this, BbCodePreviewPlugin::class);

        return $bbCodePreview->actionPreview($reportComment->message, 'report_comment', $reportComment->User, $attachments, $report->canViewAttachments());
    }

    /**
     * @param int   $reportId
     * @param array $extraWith
     * @return \XF\Entity\Report
     * @throws ReplyException
     */
    protected function assertViewableReport($reportId, array $extraWith = [])
    {
        // avoid N+1 look up behaviour, just cache all node perms
        $visitor = \XF::visitor();
        $visitor->cacheNodePermissions();
        $extraWith[] = 'Permissions|' . $visitor->permission_combination_id;

        return parent::assertViewableReport($reportId, $extraWith);
    }

    /**
     * @param int   $reportCommentId
     * @param array $extraWith
     * @return ExtendedReportCommentEntity
     * @throws ReplyException
     */
    protected function assertViewableReportComment(int $reportCommentId, array $extraWith = []): ExtendedReportCommentEntity
    {
        // avoid N+1 look up behaviour, just cache all node perms
        $visitor = \XF::visitor();
        $visitor->cacheNodePermissions();

        $extraWith[] = 'Report';
        $extraWith[] = 'Report.Permissions|' . $visitor->permission_combination_id;

        /** @var ExtendedReportCommentEntity $reportComment */
        $reportComment = Helper::find(ReportCommentEntity::class, $reportCommentId, $extraWith);
        if (!$reportComment)
        {
            throw $this->exception($this->noPermission());
        }

        if (!$reportComment->Report || !$reportComment->Report->canView())
        {
            throw $this->exception($this->noPermission());
        }

        return $reportComment;
    }
}
