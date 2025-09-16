<?php

namespace SV\ReportImprovements\Service\WarningLog;

use SV\ForumBan\Entity\ForumBan as ForumBanEntity;
use SV\ReportImprovements\Entity\IReportResolver;
use SV\ReportImprovements\Entity\WarningLog as WarningLogEntity;
use SV\ReportImprovements\Enums\WarningType;
use SV\ReportImprovements\Globals;
use SV\ReportImprovements\SV\ForumBan\Entity\ForumBan as ExtendedForumBanEntity;
use SV\ReportImprovements\XF\Entity\Post as ExtendedPostEntity;
use SV\ReportImprovements\XF\Entity\Report as ExtendedReportEntity;
use SV\ReportImprovements\XF\Entity\ReportComment as ExtendedReportCommentEntity;
use SV\ReportImprovements\XF\Entity\ThreadReplyBan as ExtendedThreadReplyBanEntity;
use SV\ReportImprovements\XF\Entity\Warning as ExtendedWarningEntity;
use SV\ReportImprovements\XF\Service\Report\Commenter as ExtendedReportCommenterService;
use SV\ReportImprovements\XF\Service\Report\Creator as ExtendedReportCreatorService;
use SV\StandardLib\Helper;
use XF\App;
use XF\Entity\Post as PostEntity;
use XF\Entity\Report as ReportEntity;
use XF\Entity\ReportComment as ReportCommentEntity;
use XF\Entity\ThreadReplyBan as ThreadReplyBanEntity;
use XF\Entity\Warning as WarningEntity;
use XF\Phrase;
use XF\PrintableException;
use XF\Service\AbstractService;
use XF\Service\Report\Commenter as ReportCommenterService;
use XF\Service\Report\Creator as ReportCreatorService;
use XF\Service\ValidateAndSavableTrait;
use function array_unshift;
use function count;
use function is_callable;
use function is_numeric;
use function join;
use function strlen;

class Creator extends AbstractService
{
    use ValidateAndSavableTrait;

    /**
     * @var WarningEntity|ExtendedWarningEntity
     */
    protected $warning;

    /**
     * @var ThreadReplyBanEntity|ExtendedThreadReplyBanEntity
     */
    protected $threadReplyBan;

    /**
     * @var ForumBanEntity|ExtendedForumBanEntity
     */
    protected $forumBan;

    /**
     * @var string
     */
    protected $operationType;

    /**
     * @var WarningLogEntity
     */
    protected $warningLog;

    /** @var ExtendedReportEntity */
    protected $report;

    /** @var ExtendedReportCommentEntity */
    protected $reportComment;

    /** @var ReportCreatorService|ExtendedReportCreatorService */
    protected $reportCreator;

    /**
     * @var ReportCommenterService|ExtendedReportCommenterService
     */
    protected $reportCommenter;

    /** @var bool */
    protected $autoResolve;
    /** @var bool */
    protected $alertOnResolve = false;
    /** @var string */
    protected $alertCommentOnResolve = '';

    /** @var bool|null */
    protected $autoResolveNewReports = null;

    /** @var bool */
    protected $canReopenReport = true;

    /**
     * Creator constructor.
     *
     * @param App         $app
     * @param IReportResolver $content
     * @param string          $operationType
     * @throws \Exception
     */
    public function __construct(App $app, IReportResolver $content, string $operationType)
    {
        parent::__construct($app);

        $this->operationType = $operationType;
        $this->setContent($content);
        $this->setupDefaults();
    }

    protected function setContent(IReportResolver $content)
    {
        if ($content instanceof WarningEntity)
        {
            $this->warning = $content;
        }
        else if ($content instanceof ThreadReplyBanEntity)
        {
            $this->threadReplyBan = $content;
        }
        else if ($content instanceof ForumBanEntity)
        {
            $this->forumBan = $content;
        }
        else
        {
            throw new \LogicException('Unsupported content type provided.');
        }
    }

    public function setAutoResolve(bool $autoResolve, bool $alert, string $alertComment)
    {
        $this->autoResolve = $autoResolve;
        $this->alertOnResolve = $alert;
        $this->alertCommentOnResolve = $alertComment;

        if ($alert)
        {
            if ($this->reportCommenter)
            {
                $this->reportCommenter->setupClosedAlert($alertComment);
            }
            else if ($this->reportCreator)
            {
                // store even if the alert isn't actually sent
                $this->reportCreator->getComment()->bulkSet([
                    'alertSent'    => true,
                    'alertComment' => $alertComment,
                ], ['forceSet' => true]);
            }
        }
    }

    public function setAutoResolveNewReports(bool $autoResolve)
    {
        $this->autoResolveNewReports = $autoResolve;
    }

    /**
     * @return string[]
     */
    protected function getFieldsToLog(): array
    {
        return [
            'content_type',
            'content_id',
            'content_title',
            'user_id',
            'warning_id',
            'warning_date',
            'warning_user_id',
            'warning_definition_id',
            'title',
            'notes',
            'points',
            'expiry_date',
            'is_expired',
            'extra_user_group_ids',
        ];
    }

    /**
     * @throws \Exception
     */
    protected function setupDefaults()
    {
        $this->warningLog = Helper::createEntity(WarningLogEntity::class);
        $warningLog = $this->warningLog;
        $warningLog->operation_type = $this->operationType;
        $warningLog->warning_edit_date = $this->operationType === WarningType::New ? 0 : \XF::$time;

        $report = null;
        $oldValue = Globals::$suppressReportStateChange;
        Globals::$suppressReportStateChange = true;
        try
        {
            if ($this->warning)
            {
                $report = $this->setupDefaultsForWarning();
            }
            else if ($this->threadReplyBan)
            {
                $report = $this->setupDefaultsForThreadReplyBan();
            }
            else if ($this->forumBan)
            {
                $report = $this->setupDefaultsForForumBan();
            }
        }
        finally
        {
            Globals::$suppressReportStateChange = $oldValue;
        }

        if ($this->reportCommenter)
        {
            if (is_callable([$this->reportCommenter, 'setAutoReport']))
            {
                $this->reportCommenter->setAutoReport(true);
            }
            $this->reportComment = $this->reportCommenter->getComment();
            $this->report = $this->reportCommenter->getReport();
        }
        else if ($this->reportCreator)
        {
            if (is_callable([$this->reportCreator, 'setAutoReport']))
            {
                $this->reportCreator->setAutoReport(true);
            }
            $this->reportComment = $this->reportCreator->getComment();
            $this->report = $this->reportCreator->getReport();
        }

        if ($this->reportComment)
        {
            $this->reportComment->warning_log_id = $warningLog->getDeferredPrimaryId();
            $this->reportComment->hydrateRelation('WarningLog', $warningLog);
            if ($report)
            {
                $this->reportComment->hydrateRelation('Report', $report);
            }
        }

        // set the message after so the reportComment.warning_log_id is set
        if ($this->reportCommenter)
        {
            $this->reportCommenter->setMessage('', false);
        }
        else if ($this->reportCreator)
        {
            $this->reportCreator->setMessage('', false);
        }
    }

    public function setCanReopenReport($canReopen)
    {
        $this->canReopenReport = $canReopen;
    }

    protected function getWarnedContentPublicBanner(): ?string
    {
        /** @var ?string $publicBanner */
        $publicBanner = $this->warning->getOption('svPublicBanner');
        if ($publicBanner === null)
        {
            $publicBanner = (string)($this->warning->Content->warning_message ?? '');
        }

        if ($publicBanner === '')
        {
            $publicBanner = null;
        }

        return $publicBanner;
    }

    /**
     * @return ExtendedReportEntity|ReportEntity|null
     */
    protected function setupDefaultsForWarning()
    {
        $warningLog = $this->warningLog;
        $warning = $this->warning;
        $report = $warning->Report;

        $warningLog->hydrateRelation('Warning', $warning);
        $warningLog->hydrateRelation('User', $warning->User);

        foreach ($this->getFieldsToLog() AS $field)
        {
            if ($warning->offsetExists($field))
            {
                $fieldValue = $warning->get($field);
                $warningLog->set($field, $fieldValue);
            }
        }
        $warningLog->public_banner = $this->getWarnedContentPublicBanner();

        if ($report)
        {
            $this->reportCommenter = Helper::service(ReportCommenterService::class, $report);
        }
        else if ((\XF::app()->options()->sv_report_new_warnings ?? false) && $warning->Content)
        {
            $this->reportCreator = Helper::service(ReportCreatorService::class, $warning->content_type, $warning->Content);
            $report = $this->reportCreator->getReport();

            $warning->clearCache('Report');
            $warning->hydrateRelation('Report', $report);
        }

        return $report;
    }


    protected function isLoggingReplyBanLinkToReportComment(): bool
    {
        return \XF::config('svIsLoggingReplyBanLinkToReportComment') ?? true;
    }

    protected function isLoggingForumBanLinkToReportComment(): bool
    {
        return (Helper::isAddOnActive('SV/ForumBan')
                && (\XF::config('svIsLoggingForumBanLinkToReportComment') ?? true)
        );
    }

    /**
     * @return ExtendedReportEntity|null
     */
    protected function setupDefaultsForThreadReplyBan(): ?ExtendedReportEntity
    {
        $warningLog = $this->warningLog;
        $threadReplyBan = $this->threadReplyBan;
        $warningLog->warning_date = \XF::$time;

        $report = $threadReplyBan->Report;
        $user = $threadReplyBan->User;
        $content = $user;
        $contentTitle = $user->username;

        $warningLog->hydrateRelation('ReplyBan', $threadReplyBan);
        $warningLog->hydrateRelation('ReplyBanThread', $threadReplyBan->Thread);
        $warningLog->hydrateRelation('User', $user);

        /** @var ExtendedPostEntity $post */
        $post = $threadReplyBan->Post;
        if ($post)
        {
            $warningLog->hydrateRelation('ReplyBanPost', $post);

            $report = $post->Report;
            $content = $post;
            $contentTitle = $post->Thread->title;
        }

        $warningLog->content_type = $content->getEntityContentType();
        $warningLog->content_id = $content->getExistingEntityId();
        $warningLog->content_title = $contentTitle;
        $warningLog->public_banner = $threadReplyBan->getOption('svPublicBanner');
        $warningLog->expiry_date = (int)$threadReplyBan->expiry_date;
        $warningLog->is_expired = $threadReplyBan->expiry_date > \XF::$time;
        $warningLog->reply_ban_thread_id = $threadReplyBan->thread_id;
        $warningLog->reply_ban_post_id = $content instanceof PostEntity ? $content->getEntityId() : null;
        $warningLog->user_id = $threadReplyBan->user_id;
        $warningLog->warning_user_id = \XF::visitor()->user_id;
        $warningLog->warning_definition_id = null;
        $warningLog->title = \XF::phrase('svReportImprov_reply_banned')->render('raw');

        $notes = '';
        if ($this->isLoggingReplyBanLinkToReportComment())
        {
            $notes .= $warningLog->getReplyBanLink() . "\n";
        }
        $notes .= $threadReplyBan->reason;
        $warningLog->notes = $notes;

        if ($report)
        {
            $this->reportCommenter = Helper::service(ReportCommenterService::class, $report);
        }
        else
        {
            $this->reportCreator = Helper::service(ReportCreatorService::class, $content->getEntityContentType(), $content);
            $report = $this->reportCreator->getReport();
        }

        $threadReplyBan->clearCache('Report');
        $threadReplyBan->hydrateRelation('Report', $report);

        return $report;
    }



    /**
     * @return ExtendedReportEntity|null
     */
    protected function setupDefaultsForForumBan()
    {
        $warningLog = $this->warningLog;
        $forumBan = $this->forumBan;
        $warningLog->warning_date = \XF::$time;

        $report = $forumBan->Report;
        $user = $forumBan->User;
        $content = $user;
        $contentTitle = $user->username;

        $warningLog->hydrateRelation('ForumBan', $forumBan);
        $warningLog->hydrateRelation('ForumBanForum', $forumBan->Forum);
        $warningLog->hydrateRelation('User', $user);

        $warningLog->content_type = $content->getEntityContentType();
        $warningLog->content_id = $content->getExistingEntityId();
        $warningLog->content_title = $contentTitle;
        $warningLog->public_banner = $forumBan->getOption('svPublicBanner');
        $warningLog->expiry_date = (int)$forumBan->expiry_date;
        $warningLog->is_expired = $forumBan->expiry_date > \XF::$time;
        $warningLog->reply_ban_node_id = $forumBan->node_id;
        $warningLog->user_id = $forumBan->user_id;
        $warningLog->warning_user_id = \XF::visitor()->user_id;
        $warningLog->warning_definition_id = null;
        $warningLog->title = \XF::phrase('svReportImprov_forum_banned')->render('raw');

        $notes = '';
        if ($this->isLoggingForumBanLinkToReportComment())
        {
            $notes .= $warningLog->getForumBanLink() . "\n";
        }
        $notes .= $forumBan->reason;
        $warningLog->notes = $notes;

        if ($report)
        {
            $this->reportCommenter = Helper::service(ReportCommenterService::class, $report);
        }
        else
        {
            $this->reportCreator = Helper::service(ReportCreatorService::class, $content->getEntityContentType(), $content);
            $report = $this->reportCreator->getReport();
        }

        $forumBan->clearCache('Report');
        $forumBan->hydrateRelation('Report', $report);

        return $report;
    }

    /**
     * @return WarningLogEntity
     */
    public function getWarningLog(): WarningLogEntity
    {
        return $this->warningLog;
    }

    public function getReport(): ExtendedReportEntity
    {
        return $this->report;
    }

    /**
     * @return array
     */
    protected function _validate()
    {
        $showErrorException = function ($errorFor, array $errors, array &$errorOutput) {
            if (count($errors))
            {
                foreach ($errors as $key => $error)
                {
                    if ($error instanceof Phrase)
                    {
                        $error = $error->render('raw');
                    }
                    if (is_numeric($key))
                    {
                        $errorOutput[] = "{$errorFor}: {$error}";
                    }
                    else
                    {
                        $errorOutput[] = "{$errorFor}-{$key}: {$error}";
                    }
                }
            }
        };

        $oldVal = Globals::$forceSavingReportComment;
        Globals::$forceSavingReportComment = true;
        try
        {
            $this->warningLog->preSave();
            $warningLogErrors = $this->warningLog->getErrors();
            $reportCreatorErrors = [];
            $reportCommenterErrors = [];

            if ($this->reportCreator)
            {
                $this->reportCreator->validate($reportCreatorErrors);
            }
            else if ($this->reportCommenter)
            {
                $this->reportCommenter->validate($reportCommenterErrors);
            }
        }
        finally
        {
            Globals::$forceSavingReportComment = $oldVal;
        }
        $errorOutput = [];
        $showErrorException('Warning log', $warningLogErrors, $errorOutput);
        $showErrorException('Report', $reportCreatorErrors, $errorOutput);
        $showErrorException('Report comment', $reportCommenterErrors, $errorOutput);
        if ($errorOutput)
        {
            if ($this->warning)
            {
                array_unshift($errorOutput, "Warning:{$this->warning->warning_id}");
            }
            throw new \RuntimeException(join(", \n", $errorOutput));
        }

        return [];
    }

    /**
     * @return WarningLogEntity
     * @throws PrintableException
     * @throws \Exception
     */
    protected function _save(): WarningLogEntity
    {
        \XF::db()->beginTransaction();

        $this->warningLog->save(true, false);
        $report = null;
        if ($this->reportCreator)
        {
            $this->_saveReport();
            /** @var ReportEntity $report */
            $report = $this->reportCreator->save();
        }
        else if ($this->reportCommenter)
        {
            $this->_saveReportComment();
            /** @var ReportCommentEntity $comment */
            $comment = $this->reportCommenter->save();
            $report = $comment ? $comment->Report : null;
        }

        if ($report)
        {
            if ($this->warning)
            {
                $this->warning->clearCache('Report');
                $this->warning->hydrateRelation('Report', $report);
            }
            else if ($this->threadReplyBan)
            {
                $this->threadReplyBan->clearCache('Report');
                $this->threadReplyBan->hydrateRelation('Report', $report);
            }
            else if ($this->forumBan)
            {
                $this->forumBan->clearCache('Report');
                $this->forumBan->hydrateRelation('Report', $report);
            }
        }

        \XF::db()->commit();

        return $this->warningLog;
    }

    protected function getNextReportState(bool $newReport): string
    {
        $autoResolve = $this->autoResolve;
        if ($newReport && $this->autoResolveNewReports !== null)
        {
            $autoResolve = $this->autoResolveNewReports;
        }

        $newReportState = '';
        if ($autoResolve)
        {
            $newReportState = 'resolved';
        }

        // don't re-open the report when a warning expires naturally.
        if ($this->operationType === WarningType::Expire || $this->operationType === WarningType::Acknowledge)
        {
            $newReportState = '';
        }
        else
        {
            $report = $this->report;
            if ($newReportState === '' && ($report->report_state === 'resolved' || $report->report_state === 'rejected'))
            {
                // re-open an existing report. If assigned, do not change to an 'assigned' state
                $newReportState = $this->canReopenReport ? 'open' : '';
            }
            // do not change the report state to something it already is
            if ($newReportState !== '' && $report->report_state === $newReportState)
            {
                $newReportState = '';
            }
        }

        if ($this->report->report_state !== 'resolved' &&
            ($this->reportComment->state_change === 'resolved' || $newReportState === 'resolved'))
        {
            $newReportState = 'resolved';
        }

        return $newReportState;
    }

    protected function _saveReport()
    {
        $resolveState = $this->getNextReportState(true);

        $this->reportComment->bulkSet([
            'warning_log_id' => $this->warningLog->warning_log_id,
            'is_report'      => false,
            'state_change'   => $resolveState,
        ], ['forceSet' => true]);

        if (strlen($resolveState) !== 0)
        {
            $report = $this->report;
            $report->set('report_state', $resolveState, ['forceSet' => true]);
            // if Report Centre Essentials is installed, then mark this as an autoreport
            if (isset($report->structure()->columns['autoreported']))
            {
                $report->set('autoreported', true, ['forceSet' => true]);
            }
        }
    }

    protected function _saveReportComment()
    {
        $resolveState = $this->getNextReportState(false);

        $this->reportComment->bulkSet([
            'warning_log_id' => $this->warningLog->warning_log_id,
            'is_report'      => false,
            'state_change'   => $resolveState,
        ], ['forceSet' => true]);

        if (strlen($resolveState) !== 0)
        {
            $this->report->set('report_state', $resolveState, ['forceSet' => true]);
            $this->reportComment->addCascadedSave($this->report);
        }

        // XF\Service\Report\Commenter::finalSetup skips recording/sending the alert, as the comment state hasn't been updated
        if ($this->reportCommenter->isSendAlert() &&
            !$this->reportComment->alertSent && $this->reportComment->isClosureComment())
        {
            $this->reportComment->bulkSet([
                'alertSent' => true,
                'alertComment' => $this->reportCommenter->getAlertComment(),
            ], ['forceSet' => true]);
        }
    }

    /**
     * @throws \Exception
     */
    public function sendNotifications()
    {
        if ($this->reportCreator)
        {
            $this->reportCreator->sendNotifications();
        }
        else if ($this->reportCommenter)
        {
            $this->reportCommenter->sendNotifications();
        }
    }
}