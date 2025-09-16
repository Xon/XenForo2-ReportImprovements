<?php

namespace SV\ReportImprovements\XF\Repository;

use XF\Entity\Thread as ThreadEntity;
use XF\Finder\Post as PostFinder;
use function is_callable;

/**
 * @extends \XF\Repository\Post
 */
class Post extends XFCP_Post
{
    public function findPostsForThreadView(ThreadEntity $thread, array $limits = [])
    {
        return $this->svShimForReportedPostBanner(parent::findPostsForThreadView($thread, $limits));
    }

    public function findSpecificPostsForThreadView(ThreadEntity $thread, array $postIds, array $limits = [])
    {
        return $this->svShimForReportedPostBanner(parent::findSpecificPostsForThreadView($thread, $postIds, $limits));
    }

    public function findNewestPostsInThread(ThreadEntity $thread, $newerThan, array $limits = [])
    {
        return $this->svShimForReportedPostBanner(parent::findNewestPostsInThread($thread, $newerThan, $limits));
    }

    public function findNextPostsInThread(ThreadEntity $thread, $newerThan, array $limits = [])
    {
        return $this->svShimForReportedPostBanner(parent::findNextPostsInThread($thread, $newerThan, $limits));
    }

    protected function svShimForReportedPostBanner(PostFinder $finder): PostFinder
    {
        if (\XF::options()->svReportedPostBanner ?? false)
        {
            $visitor = \XF::visitor();
            $userId = (int)$visitor->user_id;
            if ($userId !== 0 && is_callable([$visitor, 'canViewReports']) && $visitor->canViewReports())
            {
                $finder->with('Report');
            }
        }

        return $finder;
    }
}