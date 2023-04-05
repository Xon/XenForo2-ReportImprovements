<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\ReportImprovements\Attachment;

use SV\ReportImprovements\XF\Entity\Report as ReportEntity;
use SV\ReportImprovements\XF\Entity\ReportComment as ReportCommentEntity;
use XF\Attachment\AbstractHandler;
use XF\Entity\Attachment;
use XF\Mvc\Entity\Entity;

class ReportComment extends AbstractHandler
{
    public function getContainerLink(Entity $container, array $extraParams = [])
    {
        /** @var ReportCommentEntity $container */
        return \XF::app()->router()->buildLink('reports/comment', $container);
    }

    public function getContainerWith()
    {
        $visitor = \XF::visitor();

        return [
            'Report',
            'Report.Permissions|' . $visitor->permission_combination_id,
        ];
    }

    public function getReportWith()
    {
        $visitor = \XF::visitor();

        return [
            'Permissions|' . $visitor->permission_combination_id,
        ];
    }

    public function canView(Attachment $attachment, Entity $container, &$error = null)
    {
        /** @var ReportCommentEntity $container */
        if (!$container->canView())
        {
            return false;
        }

        $report = $container->Report;

        return $report && $report->canViewAttachments($error);
    }

    public function canManageAttachments(array $context, &$error = null)
    {
        $report = $this->getReportFromContext($context);

        return ($report && $report->canUploadAndManageAttachments());
    }

    public function onAttachmentDelete(Attachment $attachment, Entity $container = null)
    {
        if (!$container)
        {
            return;
        }

        /** @var ReportCommentEntity $container */
        $container->attach_count--;
        $container->save();

        \XF::app()->logger()->logModeratorAction($this->contentType, $container, 'attachment_deleted', [], false);
    }

    public function getConstraints(array $context)
    {
        /** @var \XF\Repository\Attachment $attachRepo */
        $attachRepo = \XF::repository('XF:Attachment');

        $constraints = $attachRepo->getDefaultAttachmentConstraints();

        $report = $this->getReportFromContext($context);
        if ($report && $report->canUploadVideos())
        {
            $constraints = $attachRepo->applyVideoAttachmentConstraints($constraints);
            $constraints = $this->svUpdateConstraints($constraints, $report);
        }

        return $constraints;
    }

    protected function svUpdateConstraints(array $constraints, ReportEntity $comment): array
    {
        $size = $comment->hasReportPermission('attach_size');
        if ($size > 0 && $size < $constraints['size'])
        {
            $constraints['size'] = $size * 1024;
        }
        $count = $comment->hasReportPermission('attach_count');
        if ($count > 0 && $count < $constraints['count'])
        {
            $constraints['count'] = $count;
        }

        return $constraints;
    }

    public function getContainerIdFromContext(array $context)
    {
        return (int)($context['report_comment_id'] ?? 0);
    }

    public function getContext(Entity $entity = null, array $extraContext = [])
    {
        if ($entity instanceof \XF\Entity\ReportComment)
        {
            $extraContext['report_comment_id'] = $entity->report_comment_id;
        }
        else if ($entity instanceof \XF\Entity\Report)
        {
            $extraContext['report_id'] = $entity->report_id;
        }
        else
        {
            throw new \InvalidArgumentException('Entity must be a ReportComment or Report');
        }

        return $extraContext;
    }

    /**
     * @param array $context
     * @return ReportEntity|null
     */
    protected function getReportFromContext(array $context)
    {
        $em = \XF::em();
        $reportCommentId = (int)($context['report_comment_id'] ?? 0);
        if ($reportCommentId)
        {
            /** @var ReportCommentEntity $reportComment */
            $reportComment = $em->find('XF:ReportComment', $reportCommentId, $this->getContainerWith());
            if (!$reportComment || !$reportComment->canView() || !$reportComment->canEdit())
            {
                return null;
            }

            return $reportComment->Report;
        }

        $reportId = (int)($context['report_id'] ?? 0);
        if ($reportId)
        {
            /** @var ReportEntity|null $report */
            $report = $em->find('XF:Report', $reportId, $this->getReportWith());
            if ($report === null || !$report->canView() || !$report->canComment())
            {
                return null;
            }

            return $report;
        }

        return null;
    }
}