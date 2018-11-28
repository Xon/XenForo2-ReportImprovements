<?php

namespace SV\ReportImprovements\XF\ControllerPlugin;

use SV\ReportImprovements\Globals;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Reply\View;

/**
 * Class Warn
 * 
 * Extends \XF\ControllerPlugin\Warn
 *
 * @package SV\ReportImprovements\XF\ControllerPlugin
 */
class Warn extends XFCP_Warn
{
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
        }

        if ($this->request->exists('ban_length'))
        {
            $inputData['ban_length'] = 'str';
            $inputData['ban_length_value'] = 'uint';
            $inputData['ban_length_unit'] = 'str';

            $inputData['reply_ban_send_alert'] = 'bool';
            $inputData['reply_ban_reason'] = 'str';
        }

        return array_merge($warningSubmitInput, $this->filter($inputData));
    }

    /**
     * @param \XF\Warning\AbstractHandler $warningHandler
     * @param \XF\Entity\User             $user
     * @param string                      $contentType
     * @param Entity                      $content
     * @param array                       $input
     *
     * @return \XF\Service\User\Warn
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function setupWarnService(\XF\Warning\AbstractHandler $warningHandler, \XF\Entity\User $user, $contentType, Entity $content, array $input)
    {
        $warnService = parent::setupWarnService($warningHandler, $user, $contentType, $content, $input);

        if (isset($input['resolve_report']))
        {
            /** @var \SV\ReportImprovements\XF\Entity\Report $report */
            $report = $this->finder('XF:Report')
                ->where('content_type', $contentType)
                ->where('content_id', $content->getEntityId())
                ->fetchOne();

            if ($report && !$report->canUpdate($error))
            {
                throw $this->exception($this->noPermission($error));
            }

            if ($contentType !== 'post')
            {
                Globals::$resolveWarningReport = $input['resolve_report'];
            }
        }

        if ($contentType === 'post' && isset($input['ban_length']) && $input['ban_length'] !== '')
        {
            /** @var \XF\Entity\Post $content */
            if (!$content->Thread)
            {
                throw $this->exception($this->notFound(\XF::phrase('requested_thread_not_found')));
            }

            if (!$content->Thread->canReplyBan($error))
            {
                throw $this->exception($this->noPermission($error));
            }

            /** @var \SV\ReportImprovements\XF\Service\Thread\ReplyBan $replyBanService */
            $replyBanService = $this->service('XF:Thread\ReplyBan', $content->Thread, $user);

            if ($input['ban_length'] === 'temporary')
            {
                $replyBanService->setExpiryDate($input['ban_length_unit'], $input['ban_length_value']);
            }
            else
            {
                $replyBanService->setExpiryDate(0);
            }

            if (isset($input['resolve_report']))
            {
                Globals::$resolveThreadReplyBanReport = true;
            }

            $replyBanService->setPostIdForWarning($content->post_id, $content->Thread->title);
            $replyBanService->setSendAlert($input['reply_ban_send_alert']);
            $replyBanService->setReason($input['reply_ban_reason']);
            if (!$replyBanService->validate($errors))
            {
                throw $this->exception($this->error($errors));
            }
            $replyBanService->save();
        }

        return $warnService;
    }

    /**
     * @param string $contentType
     * @param Entity $content
     * @param string $warnUrl
     * @param array  $breadcrumbs
     *
     * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|View
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
                'report' => $contentReport,
                'contentType' => $contentType,
                'contentId' => $content->getEntityId()
            ]);
        }

        return $response;
    }
}