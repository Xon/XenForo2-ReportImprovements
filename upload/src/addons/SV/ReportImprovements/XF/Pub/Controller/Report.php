<?php

namespace SV\ReportImprovements\XF\Pub\Controller;

use SV\ReportImprovements\Globals;
use XF\ControllerPlugin\Reaction as ReactionControllerPlugin;
use XF\Mvc\ParameterBag;

/**
 * Class Report
 * Extends \XF\Pub\Controller\Report
 *
 * @package SV\ReportImprovements\XF\Pub\Controller
 */
class Report extends XFCP_Report
{
    /**
     * @param              $action
     * @param ParameterBag $params
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function preDispatchController($action, ParameterBag $params)
    {
        /** @var \SV\ReportImprovements\XF\Entity\User $visitor */
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

    public function actionView(ParameterBag $params)
    {
        Globals::$shimCommentsFinder = true;
        try
        {
            return parent::actionView($params);
        }
        finally
        {
            Globals::$shimCommentsFinder = false;
        }
    }

    public function actionComment(ParameterBag $params)
    {
        // this function is to ensure XF1.x links work

        /** @var \SV\ReportImprovements\XF\Entity\Report $report */
        /** @noinspection PhpUndefinedFieldInspection */
        $report = $this->assertViewableReport($params->report_id);
        $reportComment = $this->assertViewableReportComment($this->filter('report_comment_id', 'uint'));

        $router = \XF::app()->router('public');

        if ($reportComment->report_id !== $report->report_id)
        {
            $this->redirect($router->buildLink('canonical:reports/comment', $reportComment->Report, ['report_comment_id' => $reportComment->report_comment_id]));
        }

        return $this->redirect($router->buildLink('canonical:reports', $reportComment->Report) . '#report-comment-' . $reportComment->report_comment_id);
    }

    /**
     * @param \XF\Entity\Report|\SV\ReportImprovements\XF\Entity\Report $report
     * @return \XF\Mvc\Reply\Error|\XF\Service\Report\Commenter
     * @throws \XF\Mvc\Reply\Exception
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
            /** @var \SV\ReportImprovements\XF\Entity\User $visitor */
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

        return parent::setupReportComment($report);
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionReassign(ParameterBag $params)
    {
        $this->assertPostOnly();

        /** @var \SV\ReportImprovements\XF\Entity\Report $report */
        /** @noinspection PhpUndefinedFieldInspection */
        $report = $this->assertViewableReport($params->report_id);
        if (!$report->canAssign($error))
        {
            return $this->noPermission($error);
        }

        return parent::actionReassign($params);
    }

    /**
     * @param ParameterBag $parameterBag
     *
     * @return \XF\Mvc\Reply\View
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionReact(ParameterBag $parameterBag)
    {
        /** @noinspection PhpUndefinedFieldInspection */
        $this->assertViewableReport($parameterBag->report_id);

        $reportComment = $this->assertViewableReportComment($this->filter('report_comment_id', 'uint'));
        if (!$reportComment->canReact($error))
        {
            return $this->noPermission($error);
        }

        $reactionLinkParams = ['report_comment_id' => $reportComment->report_comment_id];

        /** @var ReactionControllerPlugin $reactionControllerPlugin */
        $reactionControllerPlugin = $this->plugin('XF:Reaction');
        return $reactionControllerPlugin->actionReact(
            $reportComment,
            $this->buildLink('reports', $reportComment->Report) . '#report-comment-' . $reportComment->report_comment_id,
            $this->buildLink('reports/react', $reportComment, $reactionLinkParams),
            $this->buildLink('reports/reactions', $reportComment, $reactionLinkParams)
        );
    }

    /**
     * @param ParameterBag $params
     *
     * @return \XF\Mvc\Reply\Message|\XF\Mvc\Reply\View
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionReactions(ParameterBag $params)
    {
        /** @noinspection PhpUndefinedFieldInspection */
        $this->assertViewableReport($params->report_id);

        $reportComment = $this->assertViewableReportComment($this->filter('report_comment_id', 'uint'));

        $breadcrumbs = $reportComment->Report->getBreadcrumbs();
        $title = \XF::phrase('sv_members_who_reacted_this_report_comment');

        /** @var ReactionControllerPlugin $reactionControllerPlugin */
        $reactionControllerPlugin = $this->plugin('XF:Reaction');
        return $reactionControllerPlugin->actionReactions(
            $reportComment,
            'reports/reactions',
            $title,
            $breadcrumbs,
            ['report_comment_id' => $reportComment->report_comment_id]
        );
    }

    public function actionConversationJoin(ParameterBag $params)
    {
        /** @noinspection PhpUndefinedFieldInspection */
        /** @var \SV\ReportImprovements\XF\Entity\Report $report */
        $report = $this->assertViewableReport($params->report_id);

        if (!$report->canJoinConversation())
        {
            return $this->notFound();
        }

        /** @var \XF\Entity\ConversationMessage $conversationMessage */
        $conversationMessage = $report->Content;
        if (!$conversationMessage || !$conversationMessage->Conversation)
        {
            return $this->notFound();
        }

        if ($this->isPost())
        {
            /** @var \XF\Service\Conversation\Inviter $service */
            $service = \XF::service('XF:Conversation\Inviter', $conversationMessage->Conversation, $conversationMessage->Conversation->Starter);
            $service->setAutoSendNotifications(false);
            $service->setRecipientsTrusted(\XF::visitor());
            $service->save();

            return $this->redirect(\XF::app()->router()->buildLink('conversations/messages', $conversationMessage));
        }

        return $this->view('XF:Report\XenForo_ViewPublic_Report_ConversationJoin', 'svReportImprov_conversation_join', [
            'report'       => $report,
            'conversation' => $conversationMessage->Conversation,
        ]);
    }

    protected function assertViewableReport($reportId, array $extraWith = [])
    {
        // avoid N+1 look up behaviour, just cache all node perms
        \XF::visitor()->cacheNodePermissions();

        return parent::assertViewableReport($reportId, $extraWith);
    }

    /**
     * @param       $reportCommentId
     * @param array $extraWith
     * @return \SV\ReportImprovements\XF\Entity\ReportComment
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertViewableReportComment($reportCommentId, array $extraWith = [])
    {
        // avoid N+1 look up behaviour, just cache all node perms
        \XF::visitor()->cacheNodePermissions();

        $extraWith[] = 'Report';

        /** @var \SV\ReportImprovements\XF\Entity\ReportComment $reportComment */
        $reportComment = $this->em()->find('XF:ReportComment', $reportCommentId, $extraWith);
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