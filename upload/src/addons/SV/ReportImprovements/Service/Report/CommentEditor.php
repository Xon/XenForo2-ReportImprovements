<?php

namespace SV\ReportImprovements\Service\Report;

use SV\ReportImprovements\XF\Entity\Report as ReportEntity;
use SV\ReportImprovements\XF\Entity\ReportComment as ReportCommentEntity;
use SV\StandardLib\Helper;
use XF\App;
use XF\Mvc\Entity\Repository;
use XF\Repository\EditHistory as EditHistoryRepo;
use XF\Service\AbstractService;
use SV\ReportImprovements\XF\Service\Report\CommentPreparer;
use XF\Service\ValidateAndSavableTrait;

class CommentEditor extends AbstractService
{
    use ValidateAndSavableTrait;

    /**
     * @var ReportCommentEntity
     */
    protected $comment;

    /**
     * @var ReportEntity
     */
    protected $report;

    /**
     * @var bool
     */
    protected $logEdit = true;

    /**
     * @var bool
     */
    protected $logHistory = true;

    /**
     * @var string|null
     */
    protected $oldMessage = null;

    /**
     * @var CommentPreparer
     */
    protected $commentPreparer;

    public function __construct(App $app, ReportCommentEntity $comment)
    {
        parent::__construct($app);

        $this->comment = $comment;
        $this->report = $comment->Report;
        if ($this->report === null)
        {
            throw new \LogicException('Report comment requires a report when editing');
        }
        $this->commentPreparer = Helper::service(\XF\Service\Report\CommentPreparer::class, $this->comment);
        $this->setCommentDefaults();
    }

    public function getComment(): ReportCommentEntity
    {
        return $this->comment;
    }

    public function getCommentPreparer()
    {
        return $this->commentPreparer;
    }

    public function getReport(): ReportEntity
    {
        return $this->report;
    }

    protected function setCommentDefaults()
    {
        if ($this->comment->is_report)
        {
            $this->commentPreparer->setDisableEmbedsInUserReports(\XF::options()->svDisableEmbedsInUserReports ?? true);
        }
    }

    public function setOldMessage(string $oldMessage = null): self
    {
        $this->oldMessage = $oldMessage;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getOldMessage()
    {
        return $this->oldMessage;
    }

    /**
     * @return string|null
     */
    public function getAttachmentHash()
    {
        return $this->commentPreparer->getAttachmentHash();
    }

    public function setAttachmentHash(string $hash = null): self
    {
        $this->commentPreparer->setAttachmentHash($hash);

        return $this;
    }

    public function setLogEdit(bool $logEdit): self
    {
        $this->logEdit = $logEdit;

        return $this;
    }

    public function isLoggingEdit(): bool
    {
        return $this->logEdit;
    }

    public function setLogHistory(bool $logHistory): self
    {
        $this->logHistory = $logHistory;

        return $this;
    }

    public function isLoggingHistory(): bool
    {
        return $this->logHistory;
    }

    protected function setupEditHistory(string $oldMessage)
    {
        $content = $this->getComment();
        $content->edit_count++;

        $options = \XF::options();
        if ($options->editLogDisplay['enabled'] && $this->isLoggingEdit())
        {
            $content->last_edit_user_id = \XF::visitor()->user_id;
            $content->last_edit_date = \XF::$time;
        }

        if ($options->editHistory['enabled'] && $this->isLoggingHistory())
        {
            $this->setOldMessage($oldMessage);
        }
    }

    public function setMessage(string $message, bool $format = true): self
    {
        $content = $this->getComment();
        $setupHistory = !$content->isChanged('message');
        $oldRawText = $content->message;

        $this->commentPreparer->setMessage($message, $format);

        if ($setupHistory && $content->isChanged('message') && ($oldRawText !== null))
        {
            $this->setupEditHistory($oldRawText);
        }

        return $this;
    }

    protected function finalSetup()
    {

    }

    protected function afterUpdate()
    {
        $oldMessage = $this->getOldMessage();
        if ($oldMessage !== null)
        {
            $reportComment = $this->getComment();
            // suppress the "required" flag which blocks "empty" content being saved to edit history
            $structure = $this->em()->getEntityStructure('XF:EditHistory');
            $oldMessageRequired = $structure->columns['old_text']['required'] ?? false;
            if ($oldMessageRequired)
            {
                $structure->columns['old_text']['required'] = false;
            }
            try
            {
                $this->getEditHistoryRepo()->insertEditHistory(
                    $reportComment->getEntityContentType(),
                    $reportComment->getEntityId(),
                    \XF::visitor(),
                    $oldMessage,
                    $this->app()->request()->getIp()
                );
            }
            finally
            {
                if ($oldMessageRequired)
                {
                    $structure->columns['old_text']['required'] = $oldMessageRequired;
                }
            }
        }

        $this->commentPreparer->afterUpdate();
    }

    protected function _validate() : array
    {
        $this->finalSetup();

        $content = $this->getComment();
        $content->preSave();

        return $content->getErrors();
    }

    protected function _save() : ReportCommentEntity
    {
        $db = $this->db();
        $db->beginTransaction();

        $content = $this->getComment();

        $content->save(true, false);

        $this->afterUpdate();

        $db->commit();

        return $content;
    }

    protected function app() : App
    {
        return $this->app;
    }

    /**
     * @return EditHistoryRepo|Repository
     */
    protected function getEditHistoryRepo() : EditHistoryRepo
    {
        return $this->repository('XF:EditHistory');
    }
}