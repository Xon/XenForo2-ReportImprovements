<?php

namespace SV\ReportImprovements\Service\WarningLog;

use SV\ReportImprovements\Entity\WarningLog;
use SV\ReportImprovements\Globals;
use XF\Entity\ThreadReplyBan;
use XF\Entity\Warning;
use XF\Mvc\Entity\Entity;
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
     * @var \XF\Service\Report\Commenter|\SV\ReportImprovements\XF\Service\Report\Commenter
     */
    protected $reportCommenter;

    /** @var bool */
    protected $autoResolve;

    /** @var bool|null */
    protected $autoResolveNewReports = null;

    /** @var bool */
    protected $canReopenReport = true;

    /**
     * Creator constructor.
     *
     * @param \XF\App $app
     * @param Entity  $content
     * @param         $operationType
     * @throws \Exception
     */
    public function __construct(\XF\App $app, Entity $content, $operationType)
    {
        parent::__construct($app);

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

        $this->operationType = $operationType;
        $this->setupDefaults();
    }

    /**
     * @param bool $autoResolve
     */
    public function setAutoResolve($autoResolve)
    {
        $this->autoResolve = (bool)$autoResolve;
    }

    /**
     * @param bool|null $autoResolve
     */
    public function setAutoResolveNewReports($autoResolve)
    {
        $this->autoResolveNewReports = (bool)$autoResolve;
    }

    /**
     * @return string[]
     */
    protected function getFieldsToLog()
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
        $warningLog->warning_edit_date = $this->operationType === 'new' ? 0 : \XF::$time;

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

    /**
     * @return \SV\ReportImprovements\XF\Entity\Report|\XF\Entity\Report|null
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

        if ($report)
        {
            $this->reportCommenter = $this->service('XF:Report\Commenter', $report);
        }
        else if (!$report && $this->app->options()->sv_report_new_warnings && $warning->Content)
        {
            $this->reportCreator = $this->service('XF:Report\Creator', $warning->content_type, $warning->Content);
            $report = $this->reportCreator->getReport();
        }

        return $report;
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
        $content = $threadReplyBan->User;
        $contentTitle = $threadReplyBan->User->username;

        $warningLog->hydrateRelation('ReplyBanThread', $threadReplyBan);
        $warningLog->hydrateRelation('User', $threadReplyBan->User);

        $post = $threadReplyBan->Post;
        if ($post)
        {
            $warningLog->hydrateRelation('ReplyBanPost', $post);

            $report = $post->Report;
            $content = $post;
            $contentTitle = \XF::phrase('post_in_thread_x', [
                'title' => $post->Thread->title,
            ])->render('raw');
        }

        $warningLog->content_type = $content->getEntityContentType();
        $warningLog->content_id = $content->getExistingEntityId();
        $warningLog->content_title = $contentTitle;
        $warningLog->expiry_date = (int)$threadReplyBan->expiry_date;
        $warningLog->is_expired = $threadReplyBan->expiry_date > \XF::$time;
        $warningLog->reply_ban_thread_id = $threadReplyBan->thread_id;
        $warningLog->reply_ban_post_id = $content instanceof \XF\Entity\Post ? $content->getEntityId() : 0;
        $warningLog->user_id = $threadReplyBan->user_id;
        $warningLog->warning_user_id = \XF::visitor()->user_id;
        $warningLog->warning_definition_id = null;
        $warningLog->title = \XF::phrase('svReportImprov_reply_banned')->render('raw');
        $warningLog->notes = $warningLog->getReplyBanLink() . "\n" . $threadReplyBan->reason;

        if ($report)
        {
            $this->reportCommenter = $this->service('XF:Report\Commenter', $report);
        }
        else if (!$report)
        {
            $this->reportCreator = $this->service('XF:Report\Creator', $content->getEntityContentType(), $content);
            $report = $this->reportCreator->getReport();
        }

        $threadReplyBan->hydrateRelation('Report', $report);

        return $report;
    }

    /**
     * @return WarningLog
     */
    public function getWarningLog()
    {
        return $this->warningLog;
    }

    /**
     * @return \SV\ReportImprovements\XF\Entity\Report
     */
    public function getReport()
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
                    if ($error instanceof \XF\Phrase)
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

        $oldVal = Globals::$allowSavingReportComment;
        Globals::$allowSavingReportComment = true;
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
            Globals::$allowSavingReportComment = $oldVal;
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
     * @throws \XF\PrintableException
     * @throws \Exception
     */
    protected function _save()
    {
        $this->db()->beginTransaction();

        $this->warningLog->save(true, false);
        $report = null;
        if ($this->reportCreator)
        {
            $this->_saveReport();
            /** @var \XF\Entity\Report $report */
            $report = $this->reportCreator->save();
        }
        else if ($this->reportCommenter)
        {
            $this->_saveReportComment();
            /** @var \XF\Entity\ReportComment $comment */
            $comment = $this->reportCommenter->save();
            $report = $comment ? $comment->Report : null;
        }

        if ($report && $this->warning && !$this->warning->Report)
        {
            $this->warning->hydrateRelation('Report', $report);
        }

        $this->db()->commit();

        return $this->warningLog;
    }

    /**
     * @param boolean $newReport
     * @return bool
     */
    protected function getNextReportState($newReport)
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
        if ($this->operationType === 'expire' || $this->operationType === 'acknowledge')
        {
            return '';
        }

        $report = $this->report;
        if ($newReportState === '' && ($report->report_state === 'resolved' || $report->report_state === 'rejected'))
        {
            // re-open an existing report
            $newReportState = $report->assigned_user_id
                ? 'assigned'
                : 'open';
            if ($newReportState === 'open' && !$this->canReopenReport)
            {
                $newReportState = '';
            }
        }
        // do not change the report state to something it already is
        if ($newReportState !== '' && $report->report_state === $newReportState)
        {
            $newReportState = '';
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

        if ($resolveState)
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

        if ($resolveState)
        {
            $this->report->set('report_state', $resolveState, ['forceSet' => true]);
            $this->reportComment->addCascadedSave($this->report);
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