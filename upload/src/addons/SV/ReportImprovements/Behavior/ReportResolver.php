<?php

namespace SV\ReportImprovements\Behavior;

use SV\ReportImprovements\Entity\IReportResolver;
use SV\ReportImprovements\Enums\WarningType;
use SV\ReportImprovements\Globals;
use SV\ReportImprovements\Repository\ReportQueue;
use SV\ReportImprovements\XF\Entity\Report;
use XF\Mvc\Entity\Behavior;
use XF\Mvc\Entity\Entity;

class ReportResolver extends Behavior
{
    /**
     * @param bool   $resolveReport
     * @param bool   $alert
     * @param string $alertComment
     * @return Report|null
     */
    public function resolveReportFor(bool $resolveReport, bool $alert, string $alertComment)
    {
        /** @var Entity|IReportResolver $entity */
        $entity = $this->entity;

        if ($resolveReport)
        {
            $entity->setOption('svResolveReport', true);
            $entity->setOption('svResolveReportAlert', $alert);
            $entity->setOption('svResolveReportAlertComment', $alertComment);
        }
        else
        {
            $entity->resetOption('svResolveReport');
            $entity->resetOption('svResolveReportAlert');
            $entity->resetOption('svResolveReportAlertComment');
        }

        return $entity->Report;
    }

    /**
     * @return array
     */
    protected function getDefaultConfig()
    {
        return [
            'expiryField' => 'expiry_date',
            'isExpiredField' => 'is_expired',
        ];
    }

    public function postSave()
    {
        /** @var Entity|IReportResolver $entity */
        $entity = $this->entity;

        if ($entity->getOption('svLogWarningChanges'))
        {
            $this->logToReport($this->getSvLogOperationType());
        }
    }

    public function postDelete()
    {
        /** @var Entity|IReportResolver $entity */
        $entity = $this->entity;

        if ($entity->getOption('svLogWarningChanges'))
        {
            $this->logToReport($this->getSvLogOperationType());
        }
    }

    protected function isJustExpired(): bool
    {
        $expiryField = $this->getConfig('expiryField') ?? '';
        if (\strlen($expiryField) === 0 &&
            (!$this->entity->isValidGetter($expiryField) || !$this->entity->isValidColumn($expiryField)))
        {
            return false;
        }

        $expiryDate = (int)$this->entity->get($expiryField);
        if ($expiryDate === 0)
        {
            return false;
        }

        $isExpired = false;
        $wasExpired = false;
        $isExpiredField = $this->getConfig('isExpiredField') ?? '';
        if (\strlen($isExpiredField) !== 0 &&
            (!$this->entity->isValidGetter($isExpiredField) || !$this->entity->isValidColumn($isExpiredField)))
        {
            $isExpired = (bool)$this->entity->get($isExpiredField);
            $wasExpired = (bool)$this->entity->getPreviousValue($isExpiredField);
        }

        if ($isExpired && $wasExpired)
        {
            return false;
        }

        if (\XF::$time >= $expiryDate)
        {
            return true;
        }

        return false;
    }

    protected function getSvLogOperationType(): string
    {
        if (\is_callable([$this->entity,'getSvLogOperationTypeForReportResolve']))
        {
            return (string)$this->entity->getSvLogOperationTypeForReportResolve();
        }

        return $this->getLogOperationType() ?? '';
    }

    protected function entityHasChangesToLog(): bool
    {
        $entity = $this->entity;

        return $entity->hasChanges() || $entity->getOption('svPublicBanner') !== null;
    }

    public function getLogOperationType(): ?string
    {
        $entity = $this->entity;

        $type = '';
        if ($entity->isInsert())
        {
            $type = WarningType::New;
        }
        // it is deliberate that isExpiry check occurs before delete/edit
        else if ($this->isJustExpired())
        {
            $type = WarningType::Expire;

            if (Globals::$expiringFromCron && empty(\XF::options()->svReportImpro_logNaturalWarningExpiry))
            {
                $type = null;
            }
        }
        else if ($entity->isUpdate() && $this->entityHasChangesToLog())
        {
            $type = WarningType::Edit;
        }
        else if ($entity->isDeleted())
        {
            $type = WarningType::Delete;
        }

        return $type;
    }

    public function logToReport(string $operationType)
    {
        if (\strlen($operationType) === 0)
        {
            return;
        }

        $entity = $this->entity;

        /** @var ReportQueue $reportQueueRepo */
        $reportQueueRepo = \SV\StandardLib\Helper::repository(\SV\ReportImprovements\Repository\ReportQueue::class);
        $reportQueueRepo->logToReport($this->entity, $operationType,
            (bool)$entity->getOption('svCanReopenReport'),
            (bool)$entity->getOption('svResolveReport'),
            (bool)$entity->getOption('svResolveReportAlert'),
            (string)$entity->getOption('svResolveReportAlertComment')
        );
    }
}
