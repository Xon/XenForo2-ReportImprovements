<?php

namespace SV\ReportImprovements\XF\ControllerPlugin;

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

        if ($this->request->exists('resolve_report'))
        {
            $warningSubmitInput['resolve_report'] = $this->filter('resolve_report', 'bool');
        }

        return $warningSubmitInput;
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

        if (isset($input['resolve_report']) && $input['resolve_report'])
        {
            /** @var \SV\ReportImprovements\XF\Entity\Report $report */
            $report = $this->finder('XF:Report')
                ->where('content_type', $contentType)
                ->where('content_id', $content->getEntityId())
                ->fetchOne();

            if (!$report)
            {
                throw $this->exception($this->notFound(\XF::phrase('requested_warning_not_found')));
            }

            /** @var \SV\ReportImprovements\XF\Entity\User $visitor */
            $visitor = \XF::visitor();

            if (!$report->canUpdate($error))
            {
                throw $this->exception($this->noPermission($error));
            }

            /** @var \XF\Service\Report\Commenter $reportCommenter */
            $reportCommenter = $this->service('XF:Report\Commenter', $report);
            $reportCommenter->setReportState('resolved', $visitor);
            if (!$reportCommenter->validate($errors))
            {
                throw $this->exception($this->error($errors));
            }
            $reportCommenter->save();
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

            $response->setParam('report', $contentReport);
        }

        return $response;
    }
}