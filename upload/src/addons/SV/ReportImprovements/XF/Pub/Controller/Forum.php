<?php

namespace SV\ReportImprovements\XF\Pub\Controller;

use SV\ForumBan\Service\ForumBan;
use SV\ReportImprovements\XF\ControllerPlugin\Warn as WarnPlugin;
use SV\StandardLib\Helper;
use XF\ControllerPlugin\Warn;
use XF\Mvc\Reply\Exception;

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
     * @return ForumBan|null
     * @throws Exception
     * @noinspection PhpUndefinedMethodInspection
     */
    protected function setupSvForumBan(\XF\Entity\Forum $forum): ?ForumBan
    {
        /** @var ForumBan|null $forumBanSvc */
        $forumBanSvc = parent::setupSvForumban($forum);

        if (!$forumBanSvc)
        {
            return $forumBanSvc;
        }

        $forumban = $forumBanSvc->getForumBan();

        /** @var WarnPlugin $warnPlugin */
        $warnPlugin = Helper::plugin($this, Warn::class);
        $warnPlugin->resolveReportFor($forumban);

        return $forumBanSvc;
    }
}