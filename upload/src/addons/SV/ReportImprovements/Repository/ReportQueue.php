<?php
namespace SV\ReportImprovements\Repository;

use SV\ReportImprovements\Entity\WarningLog;
use SV\ReportImprovements\XF\Entity\Post;
use SV\ReportImprovements\XF\Entity\ReportComment;
use SV\ReportImprovements\XF\Entity\Thread;
use XF\Entity\ThreadReplyBan;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Repository;

class ReportQueue extends Repository
{
    public function getFauxReportQueueList(): AbstractCollection
    {
        // todo - implement a stub
        //return new ArrayCollection([]);
        throw new \LogicException("Require Report Centre Essentials");
    }

    /**
     * @param AbstractCollection|ReportComment[] $comments
     */
    public function addReplyBansToComments(AbstractCollection $comments)
    {
        $postIds = [];
        $threadIds = [];
        $replyBanThreadIds = [];
        /** @var ReportComment $comment */
        foreach ($comments as $comment)
        {
            if ($comment->warning_log_id && ($warningLog = $comment->WarningLog))
            {
                $userId = $warningLog->user_id;

                $postId = (int)$warningLog->reply_ban_post_id;
                if ($postId !== 0)
                {
                    $postIds[$postId] = $warningLog;
                }

                $threadId = (int)$warningLog->reply_ban_thread_id;
                if ($threadId !== 0)
                {
                    $threadIds[$threadId] = $warningLog;
                    $replyBanThreadIds[$threadId . '-' . $userId] = [$warningLog, $threadId, $userId];
                }
            }
        }

        if ($postIds)
        {
            $posts = $this->app()->finder('XF:Post')
                          ->where('post_id', '=', \array_keys($postIds))
                          ->fetch();
            foreach ($postIds as $postId => $warningLog)
            {
                /** @var Post $post */
                /** @var WarningLog $warningLog */

                $post = $posts[$postId] ?? null;

                $warningLog->hydrateRelation('ReplyBanPost', $post);
            }
        }

        if ($threadIds)
        {
            $threads = $this->app()->finder('XF:Thread')
                            ->with('Forum')
                            ->where('thread_id', '=', \array_keys($threadIds))
                            ->fetch();
            foreach ($threadIds as $threadId => $warningLog)
            {
                /** @var Thread $thread */
                /** @var WarningLog $warningLog */

                $thread = $threads[$threadId] ?? null;

                $warningLog->hydrateRelation('ReplyBanThread', $thread);
                $thread->hydrateRelation('ReplyBans', new ArrayCollection([]));
            }
        }

        if ($replyBanThreadIds)
        {
            $finder = $this->app()->finder('XF:ThreadReplyBan');
            $conditions = [];
            foreach ($replyBanThreadIds as $data)
            {
                /** @var WarningLog $warningLog */
                /** @var int $threadId */
                /** @var int $userId */
                list ($warningLog, $threadId, $userId) = $data;

                $conditions[] = [
                    ['thread_id', '=', $threadId],
                    ['user_id', '=', $userId],
                ];

                // pre-fill
                $warningLog->hydrateRelation('ReplyBan', null);
            }

            $finder->whereOr($conditions);

            /** @var AbstractCollection|ThreadReplyBan[] $replyBans */
            $replyBans = $finder->fetch();
            $byThread = $replyBans->groupBy('thread_id', 'user_id');

            foreach ($replyBans as $replyBan)
            {
                $thread = $replyBan->Thread;
                if ($thread !== null)
                {
                    $replyBans = $byThread[$thread->thread_id] ?? null;
                    if ($replyBans)
                    {
                        $replyBans = new ArrayCollection($replyBans);
                    }
                    $thread->hydrateRelation('ReplyBans', $replyBans);
                }

                /** @var WarningLog $warningLog */
                $warningLog = $replyBanThreadIds[$replyBan->thread_id . '-' . $replyBan->user_id][0] ?? null;
                if ($warningLog)
                {
                    $warningLog->hydrateRelation('ReplyBan', $replyBan);
                    $warningLog->hydrateRelation('ReplyBanThread', $thread);
                }
            }
        }
    }
}