<?php

namespace SV\ReportImprovements\Behavior;

use SV\ReportImprovements\Entity\IReportResolver;
use SV\ReportImprovements\Globals;
use XF\Mvc\Entity\Behavior;
use XF\Mvc\Entity\Entity;

class ReportResolver extends Behavior
{
    /**
     * @param bool   $resolveReport
     * @param bool   $alert
     * @param string $alertComment
     * @return \SV\ReportImprovements\XF\Entity\Report|null
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
        if ($this->getOption('svLogWarningChanges'))
        {
            $this->logToReport($this->getSvLogOperationType());
        }
    }

    public function postDelete()
    {
        if ($this->getOption('svLogWarningChanges'))
        {
            $this->logToReport($this->getSvLogOperationType());
        }
    }

    /**
     * @return int|null
     */
    protected function getExpiry()
    {
        $expiryField = $this->getConfig('expiryField') ?? '';
        if (\strlen($expiryField) === 0 &&
            !$this->entity->isValidGetter($expiryField) &&
            !$this->entity->isValidColumn($expiryField))
        {
            return null;
        }

        $expiry = (int)$this->entity->get($expiryField);

        return $expiry === 0 ? null : $expiry;
    }

    protected function isExpired(): bool
    {
        $expiryField = $this->getConfig('expiryField') ?? '';
        if (\strlen($expiryField) === 0 &&
            !$this->entity->isValidGetter($expiryField) &&
            !$this->entity->isValidColumn($expiryField))
        {
            return false;
        }

        $expiryDate = (int)$this->entity->get($expiryField);
        if ($expiryDate === 0)
        {
            return false;
        }

        if (\XF::$time >= $expiryDate)
        {
            return true;
        }

        $isExpired = false;
        $isExpiredField = $this->getConfig('isExpiredField') ?? '';
        if (\strlen($isExpiredField) === 0 &&
            !$this->entity->isValidGetter($isExpiredField) &&
            !$this->entity->isValidColumn($isExpiredField))
        {
            $isExpired = (bool)$this->entity->get($isExpiredField);
        }


        return $isExpired;
    }

    protected function getSvLogOperationType(): string
    {
        if (\is_callable([$this->entity,'getSvLogOperationTypeForReportResolve']))
        {
            return (string)$this->entity->getSvLogOperationTypeForReportResolve();
        }

        return $this->getLogOperationType();
    }

    public function getLogOperationType(): string
    {
        $entity = $this->entity;

        $type = '';
        if ($entity->isInsert())
        {
            $type = 'new';
        }
        // it is deliberate that isExpiry check occurs before delete/edit
        else if ($this->isExpired())
        {
            $type = 'expire';

            if (Globals::$expiringFromCron && empty(\XF::options()->svReportImpro_logNaturalWarningExpiry))
            {
                $type = '';
            }
        }
        else if ($entity->isUpdate() && $entity->hasChanges())
        {
            $type = 'edit';
        }
        else if ($entity->isDeleted())
        {
            $type = 'delete';
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

        /** @var \SV\ReportImprovements\Repository\ReportQueue $reportQueueRepo */
        $reportQueueRepo = $this->repository('SV\ReportImprovements:ReportQueue');
        $reportQueueRepo->logToReport($this->entity, $operationType,
            (bool)$entity->getOption('svResolveReport'),
            (bool)$entity->getOption('svResolveReportAlert'),
            (string)$entity->getOption('svResolveReportAlertComment')
        );
    }
}
