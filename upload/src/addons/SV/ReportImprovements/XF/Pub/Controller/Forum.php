<?php

namespace SV\ReportImprovements\XF\Pub\Controller;

use SV\ForumBan\Service\ForumBan;
use SV\ForumBan\XF\Entity\Forum as ExtendedForumEntity;
use SV\ReportImprovements\XF\ControllerPlugin\Warn as ExtendedWarnPlugin;
use SV\StandardLib\Helper;
use XF\ControllerPlugin\Warn as WarnPlugin;
use XF\Entity\Forum as ForumEntity;
use XF\Mvc\Reply\Exception;

/**
 * @extends  \XF\Pub\Controller\Forum
 *
 */
class Forum extends XFCP_Forum
{
    /**
     * @param ExtendedForumEntity $forum
     * @return ForumBan|null
     * @throws Exception
     * @noinspection PhpUndefinedMethodInspection
     */
    protected function setupSvForumBan(ForumEntity $forum): ?ForumBan
    {
        /** @var ForumBan|null $forumBanSvc */
        $forumBanSvc = parent::setupSvForumban($forum);

        if (!$forumBanSvc)
        {
            return $forumBanSvc;
        }

        $forumBan = $forumBanSvc->getForumBan();

        /** @var ExtendedWarnPlugin $warnPlugin */
        $warnPlugin = Helper::plugin($this, WarnPlugin::class);
        $warnPlugin->resolveReportFor($forumBan);

        return $forumBanSvc;
    }
}