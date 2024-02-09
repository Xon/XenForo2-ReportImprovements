<?php

namespace SV\ReportImprovements\NF\Tickets\ApprovalQueue;

use NF\Tickets\Entity\Ticket as TicketEntity;
use SV\ReportImprovements\ApprovalQueue\IContainerToContent;
use XF\Mvc\Entity\Entity;

/**
 * @extends \NF\Tickets\ApprovalQueue\Ticket
 */
class Ticket extends XFCP_Ticket implements IContainerToContent
{
    public function getContainerToContentJoins(): array
    {
        return [
            'NF\Tickets:Ticket' => [
                'content' => 'NF\Tickets:Message',
                'contentKey' => 'first_message_id',
            ],
        ];
    }

    public function getReportableContent(Entity $content): ?Entity
    {
        assert($content instanceof TicketEntity);
        return $content->FirstMessage;
    }
}