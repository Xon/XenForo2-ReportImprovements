<?php

namespace SV\ReportImprovements\Search\Data;

use SV\ReportImprovements\XF\Entity\ReportComment as ReportCommentEntity;
use SV\ReportImprovements\Entity\WarningLog as WarningLogEntity;
use SV\ReportImprovements\XF\Repository\Report as ReportRepo;
use XF\Mvc\Entity\Entity;
use XF\Search\IndexRecord;
use XF\Search\MetadataStructure;
use XF\Search\Query\Query;
use function assert;
use function count;
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

        $message = $this->getWarningLogToMessage($warningLog);

        return IndexRecord::create('warning', $entity->report_comment_id, [
            'title'         => $warningLog->title,
            'message'       => $message,
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
    public function getContentInRange($lastId, $amount, $forView = false)
    {
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

    protected function getMetaData(\XF\Entity\ReportComment $entity)
    {
        $metaData = parent::getMetaData($entity);
        assert($entity instanceof ReportCommentEntity);
        $warningLog = $entity->WarningLog;

        if ($warningLog->points)
        {
            $metaData['points'] = $warningLog->points;
        }

        if ($warningLog->expiry_date)
        {
            $metaData['expiry_date'] = $warningLog->expiry_date;
        }

        $metaData['warned_user'] = $warningLog->user_id;

        if ($warningLog->reply_ban_thread_id)
        {
            $metaData['thread_reply_ban'] = $warningLog->reply_ban_thread_id;
        }

        if ($warningLog->reply_ban_post_id)
        {
            $metaData['post_reply_ban'] = $warningLog->reply_ban_post_id;
        }

        return $metaData;
    }

    public function setupMetadataStructure(MetadataStructure $structure): void
    {
        parent::setupMetadataStructure($structure);
        // warning bits
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

    public function getGroupByType(): string
    {
        return 'report';
    }

    public function getSearchFormTab(): ?array
    {
        /** @var \SV\ReportImprovements\XF\Entity\User $visitor */
        $visitor = \XF::visitor();

        if (!\is_callable([$visitor, 'canReportSearch']) || !$visitor->canReportSearch())
        {
            return null;
        }

        return [
            'title' => \XF::phrase('svReportImprov_search.warnings'),
            'order' => 260,
        ];
    }

    /**
     * @param Query            $query
     * @param \XF\Http\Request $request
     * @param array            $urlConstraints
     */
    public function applyTypeConstraintsFromInput(Query $query, \XF\Http\Request $request, array &$urlConstraints)
    {
        parent::applyTypeConstraintsFromInput($query, $request, $urlConstraints);

        $constraints = $request->filter([
            'c.warning.user'         => 'str',
            'c.warning.points.lower' => 'uint',
            'c.warning.points.upper' => '?uint',
        ]);

        $repo = \SV\SearchImprovements\Globals::repo();

        $repo->applyUserConstraint($query, $constraints, $urlConstraints,
            'c.warning.user', 'warned_user'
        );
        $repo->applyRangeConstraint($query, $constraints, $urlConstraints,
            'c.warning.points.lower', 'c.warning.points.upper', 'points',
            $this->getWarningLogQueryTableReference(), 'warning_log'
        );
    }
}