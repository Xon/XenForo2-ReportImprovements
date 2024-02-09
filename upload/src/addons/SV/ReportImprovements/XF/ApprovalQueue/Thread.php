<?php

namespace SV\ReportImprovements\XF\ApprovalQueue;

use SV\ReportImprovements\ApprovalQueue\IContainerToContent;
use XF\Entity\Thread as ThreadEntity;
use XF\Mvc\Entity\Entity;
use function assert;

/**
 * @extends \XF\ApprovalQueue\Thread
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
        assert($content instanceof ThreadEntity);
        return $content->FirstPost;
    }
}