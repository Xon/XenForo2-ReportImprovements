<?php

namespace SV\ReportImprovements\Service\WarningLog;

use SV\ReportImprovements\Entity\IReportResolver;
use SV\ReportImprovements\Entity\WarningLog;
use SV\ReportImprovements\Enums\WarningType;
use SV\ReportImprovements\Globals;
use SV\ReportImprovements\XF\Service\Report\Commenter;
use XF\App;
use XF\Entity\Post;
use XF\Entity\Report;
use XF\Entity\ReportComment;
use XF\Entity\ThreadReplyBan;
use XF\Entity\Warning;
use XF\Phrase;
use XF\PrintableException;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;

/**
 * Class Creator
 *
 * @package SV\ReportImprovements\XF\Service\WarningLog
 */
class Creator extends AbstractService
{
    use ValidateAndSavableTrait;

    /**
     * @var Warning|\SV\ReportImprovements\XF\Entity\Warning
     */
    protected $warning;

    /**
     * @var ThreadReplyBan|\SV\ReportImprovements\XF\Entity\ThreadReplyBan
     */
    protected $threadReplyBan;

    /**
     * @var string
     */
    protected $operationType;

    /**
     * @var WarningLog
     */
    protected $warningLog;

    /** @var \SV\ReportImprovements\XF\Entity\Report */
    protected $report;

    /** @var \SV\ReportImprovements\XF\Entity\ReportComment */
    protected $reportComment;

    /**
     * @var \XF\Service\Report\Creator|\SV\ReportImprovements\XF\Service\Report\Creator
     */
    protected $reportCreator;

    /**
     * @var \XF\Service\Report\Commenter|Commenter
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
        if ($content instanceof Warning)
        {
            $this->warning = $content;
        }
        else if ($content instanceof ThreadReplyBan)
        {
            $this->threadReplyBan = $content;
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
        $this->warningLog = $this->em()->create('SV\ReportImprovements:WarningLog');
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
        }
        finally
        {
            Globals::$suppressReportStateChange = $oldValue;
        }

        if ($this->reportCommenter)
        {
            if (\is_callable([$this->reportCommenter, 'setAutoReport']))
            {
                $this->reportCommenter->setAutoReport(true);
            }
            $this->reportComment = $this->reportCommenter->getComment();
            $this->report = $this->reportCommenter->getReport();
        }
        else if ($this->reportCreator)
        {
            if (\is_callable([$this->reportCreator, 'setAutoReport']))
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

    /**
     * @return string|null
     */
    protected function getWarnedContentPublicBanner()
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
     * @return \SV\ReportImprovements\XF\Entity\Report|Report|null
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
            $this->reportCommenter = $this->service('XF:Report\Commenter', $report);
        }
        else if (($this->app->options()->sv_report_new_warnings ?? false) && $warning->Content)
        {
            $this->reportCreator = $this->service('XF:Report\Creator', $warning->content_type, $warning->Content);
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
        return (\XF::isAddOnActive('SV/ForumBan')
            && (\XF::config('svIsLoggingForumBanLinkToReportComment') ?? true)
        );
    }

    /**
     * @return \SV\ReportImprovements\XF\Entity\Report|null
     */
    protected function setupDefaultsForThreadReplyBan()
    {
        $warningLog = $this->warningLog;
        $threadReplyBan = $this->threadReplyBan;
        $warningLog->warning_date = \XF::$time;

        $report = $threadReplyBan->Report;
        $user = $threadReplyBan->User;
        $content = $user;
        $contentTitle = $user->username;

        $warningLog->hydrateRelation('ReplyBanThread', $threadReplyBan->Thread);
        $warningLog->hydrateRelation('User', $user);

        /** @var \SV\ReportImprovements\XF\Entity\Post $post */
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
        $warningLog->reply_ban_post_id = $content instanceof Post ? $content->getEntityId() : 0;
        $warningLog->user_id = $threadReplyBan->user_id;
        $warningLog->warning_user_id = \XF::visitor()->user_id;
        $warningLog->warning_definition_id = null;
        $warningLog->title = \XF::phrase('svReportImprov_reply_banned')->render('raw');

        $notes = '';
        if ($this->isLoggingReplyBanLinkToReportComment())
        {
            $notes .= $warningLog->getReplyBanLink() . "\n";
        }
        if ($this->isLoggingForumBanLinkToReportComment())
        {
            $notes .= $warningLog->getForumBanLink() . "\n";
        }
        $notes .= $threadReplyBan->reason;
        $warningLog->notes = $notes;

        if ($report)
        {
            $this->reportCommenter = $this->service('XF:Report\Commenter', $report);
        }
        else
        {
            $this->reportCreator = $this->service('XF:Report\Creator', $content->getEntityContentType(), $content);
            $report = $this->reportCreator->getReport();
        }

        $threadReplyBan->clearCache('Report');
        $threadReplyBan->hydrateRelation('Report', $report);

        return $report;
    }

    /**
     * @return WarningLog
     */
    public function getWarningLog(): WarningLog
    {
        return $this->warningLog;
    }

    public function getReport(): \SV\ReportImprovements\XF\Entity\Report
    {
        return $this->report;
    }

    /**
     * @return array
     */
    protected function _validate()
    {
        $showErrorException = function ($errorFor, array $errors, array &$errorOutput) {
            if (\count($errors))
            {
                foreach ($errors as $key => $error)
                {
                    if ($error instanceof Phrase)
                    {
                        $error = $error->render('raw');
                    }
                    if (\is_numeric($key))
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
                \array_unshift($errorOutput, "Warning:{$this->warning->warning_id}");
            }
            throw new \RuntimeException(join(", \n", $errorOutput));
        }

        return [];
    }

    /**
     * @return WarningLog
     * @throws PrintableException
     * @throws \Exception
     */
    protected function _save(): WarningLog
    {
        $this->db()->beginTransaction();

        $this->warningLog->save(true, false);
        $report = null;
        if ($this->reportCreator)
        {
            $this->_saveReport();
            /** @var Report $report */
            $report = $this->reportCreator->save();
        }
        else if ($this->reportCommenter)
        {
            $this->_saveReportComment();
            /** @var ReportComment $comment */
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
        }

        $this->db()->commit();

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

        if (\strlen($resolveState) !== 0)
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

        if (\strlen($resolveState) !== 0)
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