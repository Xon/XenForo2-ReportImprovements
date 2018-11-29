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

    /**
     * Creator constructor.
     *
     * @param \XF\App $app
     * @param Entity  $content
     * @param         $operationType
     *
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

        $this->warningLog->operation_type = $this->operationType;
        if ($this->warningLog->operation_type === 'new')
        {
            $this->warningLog->warning_edit_date = 0;
        }
        else
        {
            $this->warningLog->warning_edit_date = \XF::$time;
        }

        if ($this->warning)
        {
            foreach ($this->getFieldsToLog() AS $field)
            {
                if ($this->warning->offsetExists($field))
                {
                    $fieldValue = $this->warning->get($field);
                    $this->warningLog->set($field, $fieldValue);
                }
            }

            $reportMessage = $this->warning->title;
            if (!empty($this->warning->notes))
            {
                $reportMessage .= "\r\n" . $this->warning->notes;
            }

            if (!$this->warning->Report && $this->app->options()->sv_report_new_warnings)
            {
                $this->reportCreator = $this->service('XF:Report\Creator', $this->warning->content_type, $this->warning->Content);
                $this->reportCreator->setMessage($reportMessage);
            }
            else if ($this->warning->Report)
            {
                $this->reportCommenter = $this->service('XF:Report\Commenter', $this->warning->Report);
                $this->reportCommenter->setMessage($reportMessage);
                if ($this->autoResolve)
                {
                    $this->reportCommenter->setReportState('resolved');
                }
            }
        }
        else if ($this->threadReplyBan)
        {
            $this->warningLog->warning_date = \XF::$time;
            $report = null;

            if (Globals::$postIdForWarningLog)
            {
                /** @var \XF\Finder\Report $reportFinder */
                $reportFinder = $this->finder('XF:Report');
                $reportFinder->where('content_type', 'post');
                $reportFinder->where('content_id', Globals::$postIdForWarningLog);
                $report = $reportFinder->fetchOne();
                if (!$report)
                {
                    $reportContent = $this->finder('XF:Post')
                        ->where('post_id', Globals::$postIdForWarningLog)
                        ->fetchOne();
                }

                $this->warningLog->content_type = 'post';
                $this->warningLog->content_id = Globals::$postIdForWarningLog;
                $this->warningLog->content_title = \XF::phrase('post_in_thread_x', [
                    'title' => Globals::$threadTitleForWarningLog]
                );
                $this->warningLog->reply_ban_post_id = Globals::$postIdForWarningLog;
            }
            else
            {
                $report = $this->threadReplyBan->Report;
                $reportContent = $this->threadReplyBan->User;

                $this->warningLog->content_type = 'user';
                $this->warningLog->content_id = $this->threadReplyBan->user_id;
                $this->warningLog->content_title = $this->threadReplyBan->User->username;
            }

            $this->warningLog->reply_ban_thread_id = $this->threadReplyBan->thread_id;
            $this->warningLog->user_id = $this->threadReplyBan->user_id;
            $this->warningLog->warning_user_id = \XF::visitor()->user_id;
            $this->warningLog->warning_definition_id = null;
            $this->warningLog->title = \XF::phrase('svReportImprov_reply_banned')->render();
            $this->warningLog->notes = $this->threadReplyBan->reason;

            $threadReplyBanReason = $this->threadReplyBan->reason ?: \XF::phrase('n_a')->render();
            if (!$report)
            {
                $this->reportCreator = $this->service(
                    'XF:Report\Creator',
                    $reportContent->getEntityContentType(),
                    $reportContent
                );
                $this->reportCreator->setMessage($threadReplyBanReason);
            }
            else if ($report)
            {
                $this->reportCommenter = $this->service('XF:Report\Commenter', $report);
                $this->reportCommenter->setMessage($threadReplyBanReason);
                if ($this->autoResolve)
                {
                    $this->reportCommenter->setReportState('resolved');
                }
            }
        }
    }

    /**
     * @return array
     * @throws \Exception
     */
    protected function _validate()
    {
        $this->warningLog->preSave();
        $warningLogErrors = $this->warningLog->getErrors();
        $reportCreatorErrors = [];
        $reportCommenterErrors = [];

        if ($this->reportCreator)
        {
            $this->reportCreator->validate($reportCreatorErrors);
        }
        else
        {
            $this->reportCommenter->validate($reportCommenterErrors);
        }
        $errors = array_merge($warningLogErrors, $reportCreatorErrors, $reportCommenterErrors);
        foreach ($errors AS $error)
        {
            // @TODO: show errors
            //\XF::dumpSimple($error->render());
        }

        return array_merge($warningLogErrors, $reportCreatorErrors, $reportCommenterErrors);
    }

    /**
     * @return WarningLog
     * @throws \XF\PrintableException
     * @throws \Exception
     */
    protected function _save()
    {
        $this->db()->beginTransaction();

        $this->warningLog->save();
        if ($this->reportCreator)
        {
            /** @var \SV\ReportImprovements\XF\Entity\ReportComment $comment */
            $comment = $this->reportCreator->getCommentPreparer()->getComment();
            $resolveState = $this->autoResolve && !$comment->Report->isClosed() ? 'resolved' : '';
            $comment->bulkSet([
                'warning_log_id' => $this->warningLog->warning_log_id,
                'is_report' => false,
                'state_change' => $resolveState,
            ], ['forceSet' => true]);

            if ($resolveState)
            {
                $comment->Report->set('report_state', $resolveState, ['forceSet' => true]);
            }

            $this->reportCreator->save();
        }
        else if ($this->reportCommenter)
        {
            /** @var \SV\ReportImprovements\XF\Entity\ReportComment $comment */
            $comment = $this->reportCommenter->getComment();
            $resolveState = $this->autoResolve && !$comment->Report->isClosed() ? 'resolved' : '';
            $comment->bulkSet([
                'warning_log_id' => $this->warningLog->warning_log_id,
                'is_report' => false,
                'state_change' => $resolveState,
            ], ['forceSet' => true]);

            if ($resolveState)
            {
                $comment->Report->set('report_state', $resolveState, ['forceSet' => true]);
                $comment->addCascadedSave($comment->Report);
            }

            $this->reportCommenter->save();
        }

        $this->db()->commit();

        return $this->warningLog;
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