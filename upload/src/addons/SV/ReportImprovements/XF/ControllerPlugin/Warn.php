<?php

namespace SV\ReportImprovements\XF\ControllerPlugin;

use SV\ReportImprovements\XF\Entity\Report as ExtendedReportEntity;
use XF\Entity\Warning as WarningEntity;
use XF\Service\Thread\ReplyBan as ReplyBanEntity;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Reply\View;

/**
 * Class Warn
 * Extends \XF\ControllerPlugin\Warn
 *
 * @package SV\ReportImprovements\XF\ControllerPlugin
 */
class Warn extends XFCP_Warn
{
    /**
     * @param WarningEntity|ReplyBanEntity $entity
     */
    public function resolveReportFor(Entity $entity, ExtendedReportEntity $report = null, callable $getReport = null)
    {
        if ($this->controller->request()->exists('resolve_report') &&
            $this->filter('resolve_report', 'bool'))
        {
            if (!$report && $getReport)
            {
                /** @var ExtendedReportEntity $report */
                $report = $getReport();
            }

            if (!$report || ($report->canView() && $report->canUpdate($error)))
            {
                $entity->setOption('svResolveReport',true);
                $entity->setOption('svResolveReportAlert', $this->filter('resolve_alert', 'bool'));
                $entity->setOption('svResolveReportAlertComment', $this->filter('resolve_alert_comment', 'str'));
            }
        }
    }

    /**
     * @return mixed
     */
    protected function getWarnSubmitInput()
    {
        $warningSubmitInput = parent::getWarnSubmitInput();

        $inputData = [];

        if ($this->request->exists('resolve_report'))
        {
            $inputData['resolve_report'] = 'bool';
            $inputData['resolve_alert'] = 'bool';
            $inputData['resolve_alert_comment'] = 'str';
        }

        if ($this->request->exists('ban_length'))
        {
            $inputData['ban_length'] = 'str';
            $inputData['ban_length_value'] = 'uint';
            $inputData['ban_length_unit'] = 'str';

            $inputData['reply_ban_send_alert'] = 'bool';
            $inputData['reply_ban_reason'] = 'str';
        }

        return \array_merge($warningSubmitInput, $this->filter($inputData));
    }

    /**
     * @param \XF\Warning\AbstractHandler $warningHandler
     * @param \XF\Entity\User             $user
     * @param string                      $contentType
     * @param Entity                      $content
     * @param array                       $input
     * @return \XF\Service\User\Warn
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function setupWarnService(\XF\Warning\AbstractHandler $warningHandler, \XF\Entity\User $user, $contentType, Entity $content, array $input)
    {
        /** @var \SV\ReportImprovements\XF\Service\User\Warn $warnService */
        $warnService = parent::setupWarnService($warningHandler, $user, $contentType, $content, $input);

        $resolveWarningReport = false;
        $alert = false;
        $alertComment = '';

        if (!empty($input['resolve_report']))
        {
            // TODO: fix me; racy
            /** @var \SV\ReportImprovements\XF\Entity\Report $report */
            $report = $this->finder('XF:Report')
                           ->where('content_type', $contentType)
                           ->where('content_id', $content->getEntityId())
                           ->fetchOne();

            $resolveWarningReport = !$report || $report->canView() && $report->canUpdate($error);

            if ($resolveWarningReport)
            {
                $alert = (bool)($input['resolve_alert'] ?? false);
                $alertComment = $input['resolve_alert_comment'] ?? '';
            }
        }
        $warnService->setResolveReport($resolveWarningReport, $alert, $alertComment);

        if ($contentType === 'post' &&
            isset($input['ban_length']) &&
            $input['ban_length'] !== '' &&
            $input['ban_length'] !== 'none')
        {
            /** @var \XF\Entity\Post $content */
            /** @var \SV\ReportImprovements\XF\Entity\Thread $thread */
            $thread = $content->Thread;
            if (!$thread || !$thread->canReplyBan($error))
            {
                throw $this->exception($this->noPermission());
            }

            if ($input['ban_length'] === 'permanent')
            {
                $input['ban_length_unit'] = 0;
                $input['ban_length_value'] = null;
            }

            $warnService->setupReplyBan(
                $input['reply_ban_send_alert'],
                $input['reply_ban_reason'],
                $input['ban_length_value'],
                $input['ban_length_unit'],
                $resolveWarningReport,
                $alert,
                $alertComment
            );
        }

        return $warnService;
    }

    /**
     * @param string $contentType
     * @param Entity $content
     * @param string $warnUrl
     * @param array  $breadcrumbs
     * @return \XF\Mvc\Reply\AbstractReply
     */
    public function actionWarn($contentType, Entity $content, $warnUrl, array $breadcrumbs = [])
    {
        $response = parent::actionWarn($contentType, $content, $warnUrl, $breadcrumbs);

        if ($response instanceof View)
        {
            /** @var \XF\Entity\Report $contentReport */
            $contentReport = $this->finder('XF:Report')
                                  ->where('content_type', $contentType)
                                  ->where('content_id', $content->getEntityId())
                                  ->with(['LastModified', 'LastModifiedUser'])
                                  ->fetchOne();

            $response->setParams([
                'content'     => $content,
                'report'      => $contentReport,
                'contentType' => $contentType,
                'contentId'   => $content->getEntityId(),
            ]);
        }

        return $response;
    }
}