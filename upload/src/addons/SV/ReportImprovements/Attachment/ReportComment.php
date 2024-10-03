<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\ReportImprovements\Attachment;

use SV\ReportImprovements\XF\Entity\Report as ExtendedReportEntity;
use SV\ReportImprovements\XF\Entity\ReportComment as ExtendedReportCommentEntity;
use SV\StandardLib\Helper;
use XF\Attachment\AbstractHandler;
use XF\Entity\Attachment as AttachmentEntity;
use XF\Entity\Report as ReportEntity;
use XF\Mvc\Entity\Entity;
use XF\Repository\Attachment as AttachmentRepo;

class ReportComment extends AbstractHandler
{
    public function getContainerLink(Entity $container, array $extraParams = [])
    {
        /** @var ExtendedReportCommentEntity $container */
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

    public function canView(AttachmentEntity $attachment, Entity $container, &$error = null)
    {
        /** @var ExtendedReportCommentEntity $container */
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

    public function onAttachmentDelete(AttachmentEntity $attachment, ?Entity $container = null)
    {
        if (!$container)
        {
            return;
        }

        /** @var ExtendedReportCommentEntity $container */
        $container->attach_count--;
        $container->save();

        \XF::app()->logger()->logModeratorAction($this->contentType, $container, 'attachment_deleted', [], false);
    }

    public function getConstraints(array $context)
    {
        /** @var AttachmentRepo $attachRepo */
        $attachRepo = Helper::repository(AttachmentRepo::class);

        $constraints = $attachRepo->getDefaultAttachmentConstraints();

        $report = $this->getReportFromContext($context);
        if ($report && $report->canUploadVideos())
        {
            $constraints = $attachRepo->applyVideoAttachmentConstraints($constraints);
            $constraints = $this->svUpdateConstraints($constraints, $report);
        }

        return $constraints;
    }

    protected function svUpdateConstraints(array $constraints, ExtendedReportEntity $comment): array
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

    public function getContext(?Entity $entity = null, array $extraContext = [])
    {
        if ($entity instanceof \XF\Entity\ReportComment)
        {
            $extraContext['report_comment_id'] = $entity->report_comment_id;
        }
        else if ($entity instanceof ReportEntity)
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
     * @return ExtendedReportEntity|null
     */
    protected function getReportFromContext(array $context)
    {
        $reportCommentId = (int)($context['report_comment_id'] ?? 0);
        if ($reportCommentId)
        {
            /** @var ExtendedReportCommentEntity $reportComment */
            $reportComment = Helper::find(\XF\Entity\ReportComment::class, $reportCommentId, $this->getContainerWith());
            if (!$reportComment || !$reportComment->canView() || !$reportComment->canEdit())
            {
                return null;
            }

            return $reportComment->Report;
        }

        $reportId = (int)($context['report_id'] ?? 0);
        if ($reportId)
        {
            /** @var ExtendedReportEntity|null $report */
            $report = Helper::find(ReportEntity::class, $reportId, $this->getReportWith());
            if ($report === null || !$report->canView() || !$report->canComment())
            {
                return null;
            }

            return $report;
        }

        return null;
    }
}