<?php

namespace SV\ReportImprovements\XF\Service\User;

use XF\Entity\User;
use XF\Entity\Warning;
use XF\Mvc\Entity\Entity;

/**
 * Class Warn
 *
 * Extends \XF\Service\User\Warn
 *
 * @package SV\ReportImprovements\XF\Service\User
 */
class Warn extends XFCP_Warn
{
    /** @var \XF\Service\Report\Creator */
    protected $reportCreator;

    /**
     * Warn constructor.
     *
     * @param \XF\App $app
     * @param User    $user
     * @param string  $contentType
     * @param Entity  $content
     * @param User    $warningBy
     */
    public function __construct(\XF\App $app, User $user, $contentType, Entity $content, User $warningBy)
    {
        parent::__construct($app, $user, $contentType, $content, $warningBy);

        $report = $this->finder('XF:Report')
            ->where('content_type', $contentType)
            ->where('content_id', $content->getEntityId())
            ->fetchOne();

        $this->warning->hydrateRelation('Report', $report);
        if (!$report)
        {
            $this->reportCreator = $this->service('XF:Report\Creator', $contentType, $content);
        }
    }

    /**
     * @param User $user
     */
    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    /**
     * @return array
     */
    protected function _validate()
    {
        $errors = parent::_validate();

        if ($this->reportCreator)
        {
            if (!$this->reportCreator->validate($reportCreatorErrors))
            {
                return $errors + $reportCreatorErrors;
            }
        }

        return $errors;
    }

    /**
     * @return \SV\ReportImprovements\XF\Entity\Warning|Warning|Entity
     */
    protected function _save()
    {

        /** @var \SV\ReportImprovements\XF\Entity\Warning $warning */
        $warning = parent::_save();

        if ($this->reportCreator)
        {
            $report = $this->reportCreator->save();
            if ($report)
            {
                $message = $this->warning->title;
                if (!empty($this->warning->notes))
                {
                    $message .= "\r\n" . $this->warning->notes;
                }

                /** @var \XF\Service\Report\Commenter $commentCreator */
                $commentCreator = $this->service('XF:Report\Commenter', $report);
                $commentCreator->setMessage($message);
                $commentCreator->setReportState('resolved', \XF::visitor());
                if ($commentCreator->validate($errors))
                {
                    $commentCreator->save();
                }
            }
        }

        return $warning;
    }
}