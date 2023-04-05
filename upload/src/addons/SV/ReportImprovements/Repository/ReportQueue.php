<?php
namespace SV\ReportImprovements\Repository;

use SV\ReportImprovements\Entity\IReportResolver;
use SV\ReportImprovements\Entity\WarningLog;
use SV\ReportImprovements\Globals;
use SV\ReportImprovements\Service\WarningLog\Creator;
use SV\ReportImprovements\XF\Entity\Post;
use SV\ReportImprovements\XF\Entity\ReportComment;
use SV\ReportImprovements\XF\Entity\Thread;
use SV\StandardLib\Helper;
use XF\Entity\ThreadReplyBan;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Repository;

class ReportQueue extends Repository
{
    /** @noinspection PhpUnusedParameterInspection */
    public function getReportAssignableNonModeratorsCacheTime(int $reportQueueId): int
    {
        return 7*86400; // 7 days
    }

    public function getReportAssignableNonModeratorsCacheKey(int $reportQueueId): string
    {
        return 'reports-assignable-' . $reportQueueId;
    }

    public function resetNonModeratorsWhoCanHandleReportCacheLater(): void
    {
        \XF::runLater(function(){
            $this->resetNonModeratorsWhoCanHandleReportCache();
        });
    }

    public function resetNonModeratorsWhoCanHandleReportCache(): void
    {
        $cache = \XF::app()->cache();
        if ($cache === null)
        {
            return;
        }

        /** @var ReportQueue $entryRepo */
        $entryRepo = $this->repository('SV\ReportImprovements:ReportQueue');
        /** @var int[] $reportQueueIds */
        $reportQueueIds = $entryRepo->getReportQueueList()->keys();
        $reportQueueIds[] = 0;

        foreach($reportQueueIds as $reportQueueId)
        {
            $key = $this->getReportAssignableNonModeratorsCacheKey($reportQueueId);
            if ($key)
            {
                $cache->delete($key);
            }
        }
    }


    public function getReportQueueList(): AbstractCollection
    {
        $addOns = \XF::app()->container('addon.cache');
        if (isset($addOns['SV/ReportCentreEssentials']))
        {
            /** @var \SV\ReportCentreEssentials\Repository\ReportQueue $entryRepo */
            $entryRepo = $this->repository('SV\ReportCentreEssentials:ReportQueue');
            return $entryRepo->findReportQueues()->fetch();
        }

        return $this->getFauxReportQueueList();
    }

    public function getFauxReportQueueList(): AbstractCollection
    {
        return new ArrayCollection([]);
    }

    /**
     * @param AbstractCollection|ReportComment[] $comments
     */
    public function addReplyBansToComments($comments)
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
                if ($thread !== null)
                {
                    $thread->hydrateRelation('ReplyBans', new ArrayCollection([]));
                }
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
                [$warningLog, $threadId, $userId] = $data;

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


    /**
     * @param Entity|IReportResolver $entity
     * @param string                 $type
     * @param bool                   $canReopenReport
     * @param bool                   $resolveReport
     * @param bool                   $alert
     * @param string                 $alertComment
     * @throws \Exception
     */
    public function logToReport(IReportResolver $entity, string $type, bool $canReopenReport, bool $resolveReport, bool $alert, string $alertComment)
    {
        $reporter = \XF::visitor();
        $options = \XF::options();
        $expiringFromCron = Globals::$expiringFromCron ?? false;
        $canReopenReport = !$expiringFromCron && $canReopenReport;
        if ($expiringFromCron || !$reporter->user_id)
        {
            $expireUserId = (int)($options->svReportImpro_expireUserId ?? 1);
            $reporter = $this->app()->find('XF:User', $expireUserId);
            if (!$reporter)
            {
                $reporter = $this->app()->find('XF:User', 1);
            }
            if (!$reporter)
            {
                $reporter = $entity->getResolveUser();
            }
            if (!$reporter)
            {
                $reporter = Helper::repo()->getUserEntity($entity);
            }
            if (!$reporter)
            {
                $reporter = \XF::visitor();
            }
        }

        \XF::asVisitor($reporter, function () use ($reporter, $entity, $type, $resolveReport, $canReopenReport, $alert, $alertComment) {
            /** @var Creator $warningLogCreator */
            $warningLogCreator = $this->app()->service('SV\ReportImprovements:WarningLog\Creator', $entity, $type);
            $warningLogCreator->setAutoResolve($resolveReport, $alert, $alertComment);
            $warningLogCreator->setCanReopenReport($canReopenReport);
            if ($warningLogCreator->validate($errors))
            {
                $warningLogCreator->save();
                \XF::runLater(function () use ($warningLogCreator, $reporter) {
                    \XF::asVisitor($reporter, function () use ($warningLogCreator) {
                        $warningLogCreator->sendNotifications();
                    });
                });
            }
        });
    }
}