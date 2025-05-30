<?php

namespace SV\ReportImprovements\NF\Tickets\Report;

use NF\Tickets\Entity\Message as TicketMessageEntity;
use NF\Tickets\Entity\Ticket as TicketEntity;
use NF\Tickets\Search\Data\Message as MessageSearch;
use SV\ReportImprovements\Report\ContentInterface;
use SV\ReportImprovements\Report\ReportSearchFormInterface;
use XF\Entity\Report as ReportEntity;
use XF\Http\Request;
use XF\Mvc\Entity\Entity;
use XF\Search\MetadataStructure;
use XF\Search\Query\Query;
use function assert;

/**
 * @extends \NF\Tickets\Report\Message
 */
class Message extends XFCP_Message implements ContentInterface, ReportSearchFormInterface
{
    /**
     * @var MessageSearch|null
     */
    protected $searchHandler = null;

    protected function getSearchHandler(): MessageSearch
    {
        if ($this->searchHandler === null)
        {
            /** @var MessageSearch $handler */
            $handler = \XF::app()->search()->handler($this->contentType);
            $this->searchHandler = $handler;
        }

        return $this->searchHandler;
    }

    /**
     * @param ReportEntity $report
     * @param Entity       $content
     */
    public function setupReportEntityContent(ReportEntity $report, Entity $content)
    {
        parent::setupReportEntityContent($report, $content);

        /** @var TicketMessageEntity $content */
        /** @var TicketEntity $ticket */
        $ticket = $content->Ticket;
        $contentInfo = $report->content_info;
        $contentInfo['message_date'] = $content->message_date;
        $contentInfo['ticket_status_id'] = $ticket->status_id;
        $report->content_info = $contentInfo;
    }

    public function getReportedContentDate(ReportEntity $report): ?int
    {
        $contentDate = $report->content_info['message_date'] ?? null;
        if ($contentDate === null)
        {
            /** @var TicketMessageEntity $content */
            $content = $report->getContent();
            if ($content === null)
            {
                return null;
            }

            $contentInfo = $report->content_info;
            $contentInfo['message_date'] = $contentDate = $content->message_date;
            $report->fastUpdate('content_info', $contentInfo);
        }

        return $contentDate;
    }

    public function getSearchFormTemplate(): string
    {
        return 'public:search_form_report_comment_nfTickets';
    }

    public function getSearchFormData(): array
    {
        return $this->getSearchHandler()->getSearchFormData();
    }

    public function applySearchTypeConstraintsFromInput(Query $query, Request $request, array $urlConstraints): void
    {
        $this->getSearchHandler()->applyTypeConstraintsFromInput($query, $request, $urlConstraints);
    }

    public function populateMetaData(ReportEntity $entity, array &$metaData): void
    {
        // see setupReportEntityContent for attributes cached on the report
        $ticketId = $entity->content_info['ticket_id'] ?? null;
        if ($ticketId !== null)
        {
            $metaData['ticket'] = $ticketId;
        }

        $categoryId = $entity->content_info['category_id'] ?? null;
        if ($categoryId !== null)
        {
            $metaData['ticketcat'] = $categoryId;
        }

        $statusId = $entity->content_info['ticket_status_id'] ?? null;
        if ($statusId !== null)
        {
            $metaData['ticket_status'] = $statusId;
        }
    }

    public function setupMetadataStructure(MetadataStructure $structure): void
    {
        $this->getSearchHandler()->setupMetadataStructure($structure);
    }
}