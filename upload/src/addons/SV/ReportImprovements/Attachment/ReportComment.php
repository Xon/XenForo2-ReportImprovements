<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\ReportImprovements\Attachment;

use SV\ReportImprovements\XF\Entity\ReportComment as ReportCommentEntity;
use XF\Attachment\AbstractHandler;
use XF\Entity\Attachment;
use XF\Mvc\Entity\Entity;

class ReportComment extends AbstractHandler
{
    public function getContainerLink(Entity $container, array $extraParams = [])
    {
        /** @var ReportCommentEntity $container */
        return \XF::app()->router()->buildLink('reports', $container->Report) . '#report-comment-' . $container->report_comment_id;
    }

    public function getContainerWith()
    {
        $visitor = \XF::visitor();

        return [
            'Report',
            'Report.Permissions|' . $visitor->permission_combination_id,
        ];
    }

    public function canView(Attachment $attachment, Entity $container, &$error = null)
    {
        /** @var ReportCommentEntity $container */
        if (!$container->canView())
        {
            return false;
        }

        return $container->canViewAttachments($error);
    }

    public function canManageAttachments(array $context, &$error = null)
    {
        $comment = $this->getReportCommentFromContext($context);

        return ($comment && $comment->canUploadAndManageAttachments());
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

        $comment = $this->getReportCommentFromContext($context);
        if ($comment && $comment->canUploadVideos())
        {
            $constraints = $attachRepo->applyVideoAttachmentConstraints($constraints);
            $constraints = $this->svUpdateConstraints($constraints, $comment);
        }

        return $constraints;
    }

    protected function svUpdateConstraints(array $constraints, ReportCommentEntity $comment): array
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
        return $context['report_comment_id'] ?? null;
    }

    public function getContext(Entity $entity = null, array $extraContext = [])
    {
        if ($entity instanceof \XF\Entity\ReportComment)
        {
            $extraContext['report_comment_id'] = $entity->report_comment_id;
        }
        else
        {
            throw new \InvalidArgumentException("Entity must be a ReportComment");
        }

        return $extraContext;
    }

    /**
     * @param array $context
     * @return ReportCommentEntity|null
     */
    protected function getReportCommentFromContext(array $context)
    {
        $em = \XF::em();
        $reportCommentId = (int)($context['report_comment_id'] ?? 0);
        if ($reportCommentId)
        {
            /** @var ReportCommentEntity $reportComment */
            $reportComment = $em->find('SV\BbCodePages:PageText', $reportCommentId, $this->getContainerWith());
            if (!$reportComment || !$reportComment->canView() || !$reportComment->canEdit())
            {
                return null;
            }

            return $reportComment;
        }

        return null;
    }
}