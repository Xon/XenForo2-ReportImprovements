<?php

namespace SV\ReportImprovements\Search\Data;

use SV\ReportImprovements\Enums\WarningType;
use SV\ReportImprovements\XF\Entity\ReportComment as ReportCommentEntity;
use SV\ReportImprovements\XF\Repository\Report as ReportRepo;
use SV\SearchImprovements\Util\Arr;
use SV\SearchImprovements\XF\Search\Query\Constraints\ExistsConstraint;
use SV\SearchImprovements\XF\Search\Query\Constraints\NotConstraint;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Entity;
use XF\Search\IndexRecord;
use XF\Search\MetadataStructure;
use XF\Search\Query\Query;
use function assert;
use function count;
use function in_array;
use function is_array;
use function is_string;
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
        if (!$this->isAddonFullyActive)
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

        $this->reportRepo->svPreloadReportComments($contents);

        return $contents;
    }

    protected function getMessage(ReportCommentEntity $entity): string
    {
        $message = parent::getMessage($entity);

        $message .= $this->getEntityToMessage($entity->WarningLog);

        return $message;
    }

    protected function getMetaData(ReportCommentEntity $entity): array
    {
        $metaData = parent::getMetaData($entity);
        assert($entity instanceof ReportCommentEntity);
        $warningLog = $entity->WarningLog;

        $metaData['warning_type'] = $warningLog->operation_type;
        $metaData['issuer_user'] = $warningLog->warning_user_id;
        if ($warningLog->expiry_date)
        {
            $metaData['expiry_date'] = $warningLog->expiry_date;
        }

        if ($warningLog->warning_id)
        {
            $metaData['points'] = $warningLog->points;
        }

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
        $structure->addField('warning_type', MetadataStructure::KEYWORD);
        $structure->addField('points', MetadataStructure::INT);
        $structure->addField('expiry_date', MetadataStructure::INT);
        $structure->addField('issuer_user', MetadataStructure::INT);
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

    protected function getSortOrders(): array
    {
        if (!$this->isUsingElasticSearch)
        {
            return [];
        }

        return [
            'expiry_date' => \XF::phrase('svSearchOrder.expiry_date'),
            'points' =>  \XF::phrase('svSearchOrder.points'),
        ];
    }

    public function getSearchFormData(): array
    {
        assert($this->isAddonFullyActive);
        $form = parent::getSearchFormData();

        $form['warningTypes'] = WarningType::getPairs();

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
            'c.warning.type'         => 'array-str',
            'c.warning.mod'          => 'str',
            'c.warning.points.lower' => 'uint',
            'c.warning.points.upper' => '?uint,empty-str-to-null',
            'c.warning.expiry_type'  => 'str',
            'c.warning.expiry.lower' => 'datetime',
            'c.warning.expiry.upper' => '?datetime,empty-str-to-null',
        ]);

        $repo = \SV\SearchImprovements\Globals::repo();

        $rawWarningTypes = $constraints['c.warning.type'];
        assert(is_array($rawWarningTypes));
        if (count($rawWarningTypes) !== 0)
        {
            $warningTypes = [];
            $types = WarningType::get();
            foreach ($rawWarningTypes as $value)
            {
                if (in_array($value, $types, true))
                {
                    $warningTypes[] = $value;
                }
            }
            if (count($warningTypes) !== 0  && count($warningTypes) < count($types))
            {
                $query->withMetadata('warning_type', $warningTypes);
                Arr::setUrlConstraint($urlConstraints, 'c.warning.type', $warningTypes);
            }
            else
            {
                Arr::unsetUrlConstraint($urlConstraints, 'c.warning.type');
            }
        }
        else
        {
            Arr::unsetUrlConstraint($urlConstraints, 'c.warning.type');
        }

        $repo->applyUserConstraint($query, $constraints, $urlConstraints,
            'c.warning.mod', 'issuer_user'
        );
        $repo->applyRangeConstraint($query, $constraints, $urlConstraints,
            'c.warning.points.lower', 'c.warning.points.upper', 'points',
            $this->getWarningLogQueryTableReference(), 'warning_log'
        );

        $expired = $constraints['c.warning.expiry_type'];
        assert(is_string($expired));
        switch ($expired)
        {
            case 'active':
                $constraints['c.warning.expiry.lower'] = \XF::$time;
                $constraints['c.warning.expiry.upper'] = null;
                break;
            case 'active_never_expires':
                $query->withMetadata(new NotConstraint(new ExistsConstraint('expiry_date')));
                // disable these options
                $constraints['c.warning.expiry.lower'] = 0;
                $constraints['c.warning.expiry.upper'] = null;
                break;
            case 'expired':
                $constraints['c.warning.expiry.lower'] = 0;
                $constraints['c.warning.expiry.upper'] = \XF::$time;
                break;
            case 'date':
                break;
            case '':
            default:
                $constraints['c.warning.expiry.lower'] = 0;
                $constraints['c.warning.expiry.upper'] = null;
                Arr::unsetUrlConstraint($urlConstraints, 'c.warning.expiry_type');
        }
        $repo->applyDateRangeConstraint($query, $constraints, $urlConstraints,
            'c.warning.expiry.lower', 'c.warning.expiry.upper', 'expiry_date',
            $this->getWarningLogQueryTableReference(), 'warning_log'
        );
    }
}