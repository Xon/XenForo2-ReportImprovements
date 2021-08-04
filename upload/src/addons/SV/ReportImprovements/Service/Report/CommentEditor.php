<?php

namespace SV\ReportImprovements\Service\Report;

use SV\ReportImprovements\XF\Entity\Report as ReportEntity;
use SV\ReportImprovements\XF\Entity\ReportComment as ReportCommentEntity;
use XF\Mvc\Entity\Repository;
use XF\Repository\EditHistory as EditHistoryRepo;
use XF\Service\AbstractService;
use XF\Service\Attachment\Preparer as AttachmentPreparerSvc;
use XF\Service\Message\Preparer as MessagePreparerSvc;
use XF\Service\ValidateAndSavableTrait;

class CommentEditor extends AbstractService
{
    use ValidateAndSavableTrait;

    /**
     * @var ReportCommentEntity
     */
    protected $content;

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
     * @var string|null
     */
    protected $attachmentHash;

    public function __construct(\XF\App $app, ReportCommentEntity $content)
    {
        parent::__construct($app);

        $this->content = $content;
        $this->report = $content->Report;
    }

    public function getComment(): ReportCommentEntity
    {
        return $this->content;
    }

    public function getReport(): ReportEntity
    {
        return $this->report;
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
        return $this->attachmentHash;
    }

    public function setAttachmentHash(string $hash = null): self
    {
        $this->attachmentHash = $hash;

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

    protected function getMessagePreparer(bool $format = true): MessagePreparerSvc
    {
        /** @var MessagePreparerSvc $preparer */
        $preparer = $this->service('XF:Message\Preparer', 'report_comment', $this->getComment());
        if (!$format)
        {
            $preparer->disableAllFilters();
        }
        //$preparer->setConstraint('allowEmpty', true);

        return $preparer;
    }

    public function setMessage(string $rawText, bool $format = true, bool $checkValidity = true): self
    {
        $content = $this->getComment();
        $setupHistory = !$content->isChanged('message');
        $oldRawText = $content->message;

        $preparer = $this->getMessagePreparer($format);
        $content->message = $preparer->prepare($rawText, $checkValidity);
        $content->embed_metadata = $preparer->getEmbedMetadata();

        $preparer->pushEntityErrorIfInvalid($content);

        if ($setupHistory && $content->isChanged('message') && ($oldRawText !== null))
        {
            $this->setupEditHistory($oldRawText);
        }

        return $this;
    }

    protected function finalSetup()
    {

    }

    protected function postSave()
    {
        $oldMessage = $this->getOldMessage();
        if ($oldMessage !== null)
        {
            $reportComment = $this->getComment();

            $this->getEditHistoryRepo()->insertEditHistory(
                $reportComment->getEntityContentType(),
                $reportComment->getEntityId(),
                \XF::visitor(),
                $oldMessage,
                $this->app()->request()->getIp()
            );
        }

        if ($this->attachmentHash)
        {
            $this->associateAttachments($this->attachmentHash);
        }
    }

    protected function associateAttachments(string $hash)
    {
        $reportComment = $this->getComment();

        $associated = $this->getAttachmentPreparerSvc()->associateAttachmentsWithContent(
            $hash,
            'report_comment',
            $reportComment->report_comment_id
        );
        if ($associated)
        {
            $reportComment->fastUpdate('attach_count', $reportComment->attach_count + $associated);
        }
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

        $this->postSave();

        $db->commit();

        return $content;
    }

    protected function app() : \XF\App
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

    /**
     * @return AbstractService|AttachmentPreparerSvc
     * @noinspection PhpReturnDocTypeMismatchInspection
     */
    protected function getAttachmentPreparerSvc()
    {
        return $this->service('XF:Attachment\Preparer');
    }
}