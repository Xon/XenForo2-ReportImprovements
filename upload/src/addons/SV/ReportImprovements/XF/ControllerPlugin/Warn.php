<?php

namespace SV\ReportImprovements\XF\ControllerPlugin;

use SV\ReportImprovements\Entity\IReportResolver;
use SV\WarningImprovements\XF\Entity\WarningDefinition;
use XF\Mvc\Entity\AbstractCollection;
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
     * @param Entity|IReportResolver $entity
     */
    public function resolveReportFor(Entity $entity)
    {
        if ($this->controller->request()->exists('resolve_report') &&
            $this->filter('resolve_report', 'bool') &&
            $entity->canResolveLinkedReport())
        {
            $entity->resolveReportFor(true, $this->filter('resolve_alert', 'bool'), $this->filter('resolve_alert_comment', 'str'));
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
        $warning = $warnService->getWarning();

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
                $input['ban_length_unit']
            );
        }

        $resolveReport = (bool)($input['resolve_report'] ?? false);
        $resolveAlert = (bool)($input['resolve_alert'] ?? false);
        $resolveAlertComment = (string)($input['resolve_alert_comment'] ?? '');

        if (!$resolveReport || !$warning->canResolveLinkedReport())
        {
            $resolveReport = false;
            $resolveAlert = false;
            $resolveAlertComment = '';
        }

        $warnService->setResolveReport($resolveReport, $resolveAlert, $resolveAlertComment);

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

            $user = $response->getParam('user');
            $warning = null;
            /** @var AbstractCollection|null $warningDefs */
            $warningDefs = $response->getParam('warnings');
            if ($user != null && $warningDefs !== null)
            {
                $addOns = \XF::app()->container('addon.cache');
                if (isset($addOns['SV/WarningImprovements']))
                {
                    $category = reset($warningDefs);
                    $warningDef = $category !== null ? reset($category) : null;
                }
                else
                {
                    $warningDef = is_array($warningDefs) ? reset($warningDefs) : $warningDefs->first();
                }
                /** @var WarningDefinition|null $warningDef */
                if ($warningDef !== null)
                {
                    /** @var \SV\ReportImprovements\XF\Service\User\Warn $warnService */
                    $warnService = $this->service('XF:User\Warn', $user, $contentType, $content, \XF::visitor());
                    $warnService->setFromDefinition($warningDef, 0, 0);
                    $warning = $warnService->getWarning();
                }
            }

            $response->setParams([
                'content'     => $content,
                'report'      => $contentReport,
                'contentType' => $contentType,
                'contentId'   => $content->getEntityId(),
                'proposedWarning' => $warning,
            ]);
        }

        return $response;
    }
}