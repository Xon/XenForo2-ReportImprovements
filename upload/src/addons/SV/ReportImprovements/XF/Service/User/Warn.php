<?php

namespace SV\ReportImprovements\XF\Service\User;

use XF\Service\Report\Commenter;
use XF\Service\Thread\ReplyBan;

/**
 * Class Warn
 * 
 * Extends \XF\Service\User\Warn
 *
 * @package SV\ReportImprovements\XF\Service\User
 */
class Warn extends XFCP_Warn
{
    /**
     * @var Commenter
     */
    protected $reportCommenter;

    /**
     * @var ReplyBan
     */
    protected $threadReplyBanCreator;

    /**
     * @param Commenter $reportCommenter
     */
    public function setReportCommenter(Commenter $reportCommenter)
    {
        $this->reportCommenter = $reportCommenter;
    }

    /**
     * @param ReplyBan $threadReplyBanCreator
     */
    public function setThreadReplyBanCreator(ReplyBan $threadReplyBanCreator)
    {
        $this->threadReplyBanCreator = $threadReplyBanCreator;
    }

    /**
     * @return array
     */
    protected function _validate()
    {
        $errors = parent::_validate();

        $reportCommenterErrors = [];
        if ($this->reportCommenter)
        {
            $this->reportCommenter->validate($reportCommenterErrors);
        }

        $threadReplyBanCreatorErrors = [];
        if ($this->threadReplyBanCreator)
        {
            $this->threadReplyBanCreator->validate($threadReplyBanCreatorErrors);
        }

        return $errors + $reportCommenterErrors + $threadReplyBanCreatorErrors;
    }

    /**
     * @return \XF\Entity\Warning|\XF\Mvc\Entity\Entity
     */
    protected function _save()
    {
        $warning = parent::_save();

        if ($warning)
        {
            if ($this->reportCommenter)
            {
                $this->reportCommenter->save();
            }

            if ($this->threadReplyBanCreator)
            {
                $this->threadReplyBanCreator->save();
            }
        }

        return $warning;
    }
}