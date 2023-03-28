<?php

namespace SV\ReportImprovements\NF\Tickets\Report;

use NF\Tickets\Search\Data\Message as MessageSearch;
use SV\ReportImprovements\Report\ContentInterface;
use SV\ReportImprovements\Report\ReportSearchFormInterface;
use XF\Entity\Report;
use XF\Mvc\Entity\Entity;
use XF\Search\MetadataStructure;
use function assert;

/**
 * Extends \NF\Tickets\Report\Message
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
            $handler = \XF::app()->search()->handler($this->contentType);
            assert($handler instanceof MessageSearch);
            $this->searchHandler = $handler;
        }

        return $this->searchHandler;
    }

    /**
     * @param Report $report
     * @param Entity $content
     */
    public function setupReportEntityContent(Report $report, Entity $content)
    {
        parent::setupReportEntityContent($report, $content);

        /** @var \NF\Tickets\Entity\Message $content */
        $ticket = $content->Ticket;
        $contentInfo = $report->content_info;
        $contentInfo['message_date'] = $content->message_date;
        $contentInfo['ticket_status_id'] = $ticket->status_id;
        $report->content_info = $contentInfo;
    }

    public function getContentDate(Report $report): ?int
    {
        $contentDate = $report->content_info['message_date'] ?? null;
        if ($contentDate === null)
        {
            /** @var \NF\Tickets\Entity\Message $content */
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

    public function applySearchTypeConstraintsFromInput(\XF\Search\Query\Query $query, \XF\Http\Request $request, array $urlConstraints): void
    {
        $this->getSearchHandler()->applyTypeConstraintsFromInput($query, $request, $urlConstraints);
    }

    public function populateMetaData(\XF\Entity\Report $entity, array &$metaData): void
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