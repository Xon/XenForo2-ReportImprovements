<?php

namespace SV\ReportImprovements\Service\WarningLog;

use SV\ReportImprovements\Entity\WarningLog;
use SV\ReportImprovements\Globals;
use XF\Entity\Warning;
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
     * @var string
     */
    protected $operationType;

    /**
     * @var WarningLog
     */
    protected $warningLog;

    /**
     * @var \SV\ReportImprovements\XF\Service\Report\Creator
     */
    protected $reportCreator;

    /**
     * @var \SV\ReportImprovements\XF\Service\Report\Commenter
     */
    protected $reportCommenter;

    /**
     * Creator constructor.
     *
     * @param \XF\App $app
     * @param Warning $warning
     * @param string $operationType
     *
     * @throws \Exception
     */
    public function __construct(\XF\App $app, Warning $warning, $operationType)
    {
        parent::__construct($app);

        $this->warning = $warning;
        $this->operationType = $operationType;
        $this->setupDefaults();
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

        $fieldsToCopy = [
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
            'extra_user_group_ids'
        ];

        foreach ($fieldsToCopy AS $field)
        {
            $fieldValue = $this->warning->get($field);
            $this->warningLog->set($field, $fieldValue);
        }

        $reportMessage = $this->warning->title;
        if (!empty($this->warning->notes))
        {
            $reportMessage .= "\r\n" . $this->warning->notes;
        }

        \XF::asVisitor($this->warning->WarnedBy, function () use($reportMessage)
        {
            if (!$this->warning->Report && $this->app->options()->sv_report_new_warnings)
            {
                $this->reportCreator = $this->service('XF:Report\Creator', $this->warning->content_type, $this->warning->Content);
                $this->reportCreator->setMessage($reportMessage);
            }
            else if ($this->warning->Report)
            {
                $this->reportCommenter = $this->service('XF:Report\Commenter', $this->warning->Report);
                $this->reportCommenter->setMessage($reportMessage);
                if ($this->warningLog->operation_type === 'new')
                {
                    $this->reportCommenter->setReportState('resolved');
                }
            }
        });
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

        \XF::asVisitor($this->warning->WarnedBy, function ()
        {
            if ($this->reportCreator)
            {
                $this->reportCreator->validate($reportCreatorErrors);
            }
            else
            {
                $this->reportCommenter->validate($reportCommenterErrors);
            }
        });

        return array_merge($warningLogErrors, $reportCreatorErrors, $reportCommenterErrors);
    }

    /**
     * @return WarningLog
     * @throws \XF\PrintableException
     * @throws \Exception
     */
    protected function _save()
    {
        $this->warningLog->save();

        \XF::asVisitor($this->warning->WarnedBy, function ()
        {
            if ($this->reportCreator)
            {
                /** @var \SV\ReportImprovements\XF\Entity\Report $report */
                if ($report = $this->reportCreator->save())
                {
                    $report->report_state = 'resolved';

                    if ($report->LastModified)
                    {
                        $report->LastModified->state_change = '';
                    }

                    $report->save();
                }
            }
            else if ($this->reportCommenter)
            {
                /** @var \SV\ReportImprovements\XF\Entity\ReportComment $reportComment */
                if ($reportComment = $this->reportCommenter->save())
                {
                    $reportComment->state_change = '';
                    $reportComment->delete();
                }
            }
        });

        return $this->warningLog;
    }
}