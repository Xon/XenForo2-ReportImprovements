<?php

namespace SV\ReportImprovements\Search\Data;

use SV\ReportImprovements\XF\Entity\ReportComment as ReportCommentEntity;
use SV\ReportImprovements\Entity\WarningLog as WarningLogEntity;
use SV\ReportImprovements\XF\Repository\Report as ReportRepo;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Entity;
use XF\Search\IndexRecord;
use XF\Search\MetadataStructure;
use XF\Search\Query\Query;
use function assert;
use function count;
use function in_array;
use function is_array;
use function reset;

/**
 * Class Warning
 *
 * @package SV\ReportImprovements\Search\Data
 */
class WarningLog extends ReportComment
{
    public function getIndexData(Entity $entity): ?IndexRecord
    {
        assert($entity instanceof ReportCommentEntity);
        $warningLog = $entity->WarningLog;
        if ($warningLog == null)
        {
            return null;
        }

        return IndexRecord::create('warning', $entity->report_comment_id, [
            'title'         => $warningLog->title,
            'message'       => $this->getMessage($entity),
            'date'          => $entity->comment_date,
            'user_id'       => $entity->user_id,
            'discussion_id' => $entity->report_id,
            'metadata'      => $this->getMetaData($entity),
        ]);
    }

    /**
     * @param int $lastId
     * @param int $amount
     * @param bool $forView
     * @return \XF\Mvc\Entity\AbstractCollection
     */
    public function getContentInRange($lastId, $amount, $forView = false): \XF\Mvc\Entity\AbstractCollection
    {
        $reportRepo = \XF::repository('XF:Report');
        if (!($reportRepo instanceof ReportRepo))
        {
            // This function may be invoked when the add-on is disabled, just return nothing
            return new ArrayCollection([]);
        }

        $entityId = \XF::app()->getContentTypeFieldValue($this->contentType, 'entity');
        if (!$entityId)
        {
            throw new \LogicException("Content type {$this->contentType} must define an 'entity' value");
        }

        $em = \XF::em();
        $key = $em->getEntityStructure($entityId)->primaryKey;
        if (is_array($key))
        {
            if (count($key) > 1)
            {
                throw new \LogicException("Entity $entityId must only have a single primary key");
            }
            $key = reset($key);
        }

        $finder = $em->getFinder($entityId)
                     ->where($key, '>', $lastId)
                     ->where('warning_log_id', '<>', null)
                     ->order($key)
                     ->with($this->getEntityWith($forView));

        $contents = $finder->fetch($amount);

        /** @var ReportRepo $reportRepo */
        $reportRepo = \XF::repository('XF:Report');
        $reportRepo->svPreloadReportComments($contents);

        return $contents;
    }

    protected function getMessage(ReportCommentEntity $entity): string
    {
        $message = parent::getMessage($entity);

        $message .= $this->getWarningLogToMessage($entity->WarningLog);

        return $message;
    }

    protected function getWarningLogToMessage(WarningLogEntity $warningLog): string
    {
        $message = '';
        foreach ($warningLog->structure()->columns as $column => $schema)
        {
            if (
                ($schema['type'] ?? '') !== Entity::STR ||
                empty($schema['allowedValues']) || // aka enums
                ($schema['noIndex'] ?? false)
            )
            {
                continue;
            }

            $value = $warningLog->get($column);
            if ($value === null || $value === '')
            {
                continue;
            }

            $message .= "\n" . $value;
        }

        return $message;
    }

    protected function getMetaData(ReportCommentEntity $entity): array
    {
        $metaData = parent::getMetaData($entity);
        assert($entity instanceof ReportCommentEntity);
        $warningLog = $entity->WarningLog;

        $metaData['warning_type'] = $warningLog->operation_type;
        $metaData['warned_user'] = $warningLog->user_id;
        $metaData['expiry_date'] = $warningLog->expiry_date <= 0 ? \PHP_INT_MAX : $warningLog->expiry_date;

        if ($warningLog->warning_id)
        {
            $metaData['is_report'] = self::REPORT_TYPE_WARNING;
            $metaData['points'] = $warningLog->points;
        }

        if ($warningLog->reply_ban_thread_id)
        {
            $metaData['is_report'] = self::REPORT_TYPE_REPLY_BAN;
            $metaData['thread_reply_ban'] = $warningLog->reply_ban_thread_id;
        }

        if ($warningLog->reply_ban_post_id)
        {
            $metaData['is_report'] = self::REPORT_TYPE_REPLY_BAN;
            $metaData['post_reply_ban'] = $warningLog->reply_ban_post_id;
        }

        return $metaData;
    }

    public function setupMetadataStructure(MetadataStructure $structure): void
    {
        parent::setupMetadataStructure($structure);
        // warning bits
        $structure->addField('warning_type', MetadataStructure::KEYWORD);
        $structure->addField('points', MetadataStructure::INT);
        $structure->addField('expiry_date', MetadataStructure::INT);
        $structure->addField('warned_user', MetadataStructure::INT);
        $structure->addField('thread_reply_ban', MetadataStructure::INT);
        $structure->addField('post_reply_ban', MetadataStructure::INT);
    }

    public function getTemplateData(Entity $entity, array $options = []): array
    {
        assert($entity instanceof ReportCommentEntity);
        $data = parent::getTemplateData($entity);
        $data['warningLog'] = $entity->WarningLog;

        return $data;
    }

    public function getSearchableContentTypes(): array
    {
        return ['warning'];
    }

    public function getSearchFormTab(): ?array
    {
        $visitor = \XF::visitor();
        if (!($visitor instanceof \SV\ReportImprovements\XF\Entity\User))
        {
            // This function may be invoked when the add-on is disabled, just return nothing to show
            return null;
        }

        if (!$visitor->canReportSearch())
        {
            return null;
        }

        return [
            'title' => \XF::phrase('svReportImprov_search.warnings'),
            'order' => 255,
        ];
    }

    public function getSearchFormData(): array
    {
        $form = parent::getSearchFormData();

        $form['warningTypes'] = WarningLogEntity::getWarningTypesPairs();

        return $form;
    }

    /**
     * @param Query            $query
     * @param \XF\Http\Request $request
     * @param array            $urlConstraints
     */
    public function applyTypeConstraintsFromInput(Query $query, \XF\Http\Request $request, array &$urlConstraints): void
    {
        parent::applyTypeConstraintsFromInput($query, $request, $urlConstraints);

        $constraints = $request->filter([
            'c.warning.type'         => 'str',
            'c.warning.user'         => 'str',
            'c.warning.points.lower' => 'uint',
            'c.warning.points.upper' => '?uint,empty-str-to-null',
            'c.warning.expiry.lower' => 'datetime',
            'c.warning.expiry.upper' => '?datetime,empty-str-to-null',
        ]);

        $repo = \SV\SearchImprovements\Globals::repo();

        $warningType = $constraints['c.warning.type'];
        if (in_array($warningType, WarningLogEntity::getWarningTypes(), true))
        {
            $query->withMetadata('warning_type', $warningType);
        }

        $repo->applyUserConstraint($query, $constraints, $urlConstraints,
            'c.warning.user', 'warned_user'
        );
        $repo->applyRangeConstraint($query, $constraints, $urlConstraints,
            'c.warning.points.lower', 'c.warning.points.upper', 'points',
            $this->getWarningLogQueryTableReference(), 'warning_log'
        );
        $repo->applyRangeConstraint($query, $constraints, $urlConstraints,
            'c.warning.expiry.lower', 'c.warning.expiry.upper', 'expiry_date',
            $this->getWarningLogQueryTableReference(), 'warning_log'
        );
    }
}