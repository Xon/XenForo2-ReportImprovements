<?php

namespace SV\ReportImprovements\XF\ApprovalQueue;

use SV\ReportImprovements\ApprovalQueue\IContainerToContent;
use XF\Entity\Thread as ThreadEntity;
use XF\Mvc\Entity\Entity;

/**
 * @extends \XF\ApprovalQueue\Thread
 * @extends IContainerToContent<ThreadEntity>
 */
class Thread extends XFCP_Thread implements IContainerToContent
{
    public function getContainerToContentJoins(): array
    {
        return [
            'XF:Thread' => [
                'content' => 'XF:Post',
                'contentKey' => 'first_post_id',
            ],
        ];
    }

    public function getReportableContent(Entity $content): ?Entity
    {
        return $content->FirstPost;
    }
}