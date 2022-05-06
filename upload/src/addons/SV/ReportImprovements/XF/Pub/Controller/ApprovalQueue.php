<?php

namespace SV\ReportImprovements\XF\Pub\Controller;

use XF\Mvc\Reply\AbstractReply;

/**
 * Extends \XF\Pub\Controller\ApprovalQueue
 */
class ApprovalQueue extends XFCP_ApprovalQueue
{
    public function actionReport(): AbstractReply
    {
        /** @var \XF\Entity\ApprovalQueue $approvalQueueItem */
        $approvalQueueItem = $this->em()->findOne('XF:ApprovalQueue', [
            'content_type' => $this->filter('content_type', 'str'),
            'content_id' => $this->filter('content_id', 'uint'),
        ]);
        if (!$approvalQueueItem)
        {
            return $this->notFound();
        }

        if (\is_callable($approvalQueueItem->Content, 'canView'))
        {
            if (!$approvalQueueItem->Content->canView($error))
            {
                return $this->noPermission($error);
            }
        }

        if (\is_callable($approvalQueueItem->Content, 'canReport'))
        {
            $canReport = $approvalQueueItem->Content->canReport($error);
        }
        else
        {
            $canReport = \XF::visitor()->canReport($error);
        }

        if (!$canReport)
        {
            return $this->noPermission($error);
        }

        /** @var \XF\ControllerPlugin\Report $reportPlugin */
        $reportPlugin = $this->plugin('XF:Report');
        return $reportPlugin->actionReport(
            $approvalQueueItem->content_type, $approvalQueueItem->Content,
            $this->buildLink('approval-queue/report', null, [
                'content_type' => $approvalQueueItem->content_type,
                'content_id' => $approvalQueueItem->content_id,
            ]),
            $this->buildLink('approval-queue')
        );
    }
}