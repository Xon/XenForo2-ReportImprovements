<?php

namespace SV\ReportImprovements\XF\Pub\Controller;

use SV\ReportImprovements\XF\ControllerPlugin\Warn as WarnPlugin;

/**
 * Class Forum
 * @extends  \XF\Pub\Controller\Forum
 *
 * @package SV\ReportImprovements\XF\Pub\Controller
 */
class Forum extends XFCP_Forum
{
    /**
     * @param \SV\ForumBan\XF\Entity\Forum $forum
     *
     * @return \SV\ForumBan\Service\ForumBan|null
     * @throws \XF\Mvc\Reply\Exception
     * @noinspection PhpUndefinedMethodInspection
     */
    protected function setupSvForumBan(\XF\Entity\Forum $forum): ?\SV\ForumBan\Service\ForumBan
    {
        /** @var \SV\ForumBan\Service\ForumBan|null $forumBanSvc */
        $forumBanSvc = parent::setupSvForumban($forum);

        if (!$forumBanSvc)
        {
            return $forumBanSvc;
        }

        $forumban = $forumBanSvc->getForumBan();

        /** @var WarnPlugin $warnPlugin */
        $warnPlugin = $this->plugin('XF:Warn');
        $warnPlugin->resolveReportFor($forumban);

        return $forumBanSvc;
    }
}