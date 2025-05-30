<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace SV\ReportImprovements\XF\Pub\Controller;

use SV\ReportImprovements\XF\Entity\ApprovalQueue as ExtendedApprovalQueueEntity;
use SV\ReportImprovements\XF\Repository\ApprovalQueue as ExtendedApprovalQueueRepo;
use SV\ReportImprovements\XF\Finder\ApprovalQueue as ApprovalQueueFinder;
use SV\StandardLib\Helper;
use XF\Entity\ApprovalQueue as ApprovalQueueEntity;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Finder;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Redirect as RedirectReply;
use XF\Repository\ApprovalQueue as ApprovalQueueRepo;
use function array_replace;
use function in_array;

/**
 * @extends \XF\Pub\Controller\ApprovalQueue
 */
class ApprovalQueue extends XFCP_ApprovalQueue
{
    /** @noinspection PhpMissingParentCallCommonInspection */
    public function actionIndex()
    {
        /** @var ExtendedApprovalQueueRepo $approvalQueueRepo */
        $approvalQueueRepo = Helper::repository(ApprovalQueueRepo::class);

        /** @var ApprovalQueueFinder $unapprovedFinder */
        $unapprovedFinder = $approvalQueueRepo->findUnapprovedContent();

        $filters = $this->getQueueFilterInput();
        $this->applyQueueFilters($unapprovedFinder, $filters);

        /** @var ApprovalQueueEntity[]|ArrayCollection<ApprovalQueueEntity> $unapprovedItems */
        $unapprovedItems = $unapprovedFinder->fetch();
        $numUnapprovedItems = $unapprovedFinder->total();

        /** @noinspection PhpUndefinedFieldInspection */
        if ($unapprovedFinder->isBasicQuery() && $numUnapprovedItems !== \XF::app()->unapprovedCounts['total'])
        {
            $approvalQueueRepo->rebuildUnapprovedCounts();
        }

        $approvalQueueRepo->addContentToUnapprovedItems($unapprovedItems);
        $approvalQueueRepo->cleanUpInvalidRecords($unapprovedItems);
        $unapprovedItems = $approvalQueueRepo->filterViewableUnapprovedItems($unapprovedItems);
        $unapprovedItemsSliced = $unapprovedItems->slice(0, 50);
        $approvalQueueRepo->addReportsToUnapprovedItems($unapprovedItems);

        $viewParams = [
            'filters' => $filters,
            'count' => $unapprovedItemsSliced->count(),
            'total' => $unapprovedItems->count(),
            'hasMore' => $unapprovedItemsSliced->count() < $unapprovedItems->count(),
            'last' => $unapprovedItemsSliced->last(),
            'unapprovedItems' => $unapprovedItemsSliced,
        ];
        return $this->view('XF:ApprovalQueue\Listing', 'approval_queue', $viewParams);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function actionLoadMore(ParameterBag $params): AbstractReply
    {
        /** @var ExtendedApprovalQueueRepo $approvalQueueRepo */
        $approvalQueueRepo = Helper::repository(ApprovalQueueRepo::class);

        /** @var ApprovalQueueFinder $unapprovedFinder */
        $unapprovedFinder = $approvalQueueRepo->findUnapprovedContent();
        $unapprovedFinder->where('content_date', '>', $this->filter('last_date', 'uint'));

        $filters = $this->getQueueFilterInput();
        $this->applyQueueFilters($unapprovedFinder, $filters);

        /** @var ApprovalQueueEntity[]|ArrayCollection<ApprovalQueueEntity> $unapprovedItems */
        $unapprovedItems = $unapprovedFinder->fetch();
        $numUnapprovedItems = $unapprovedItems->count();

        $approvalQueueRepo->addContentToUnapprovedItems($unapprovedItems);
        $approvalQueueRepo->cleanUpInvalidRecords($unapprovedItems);

        $unapprovedItems = $approvalQueueRepo->filterViewableUnapprovedItems($unapprovedItems);
        $unapprovedItemsSliced = $unapprovedItems->slice(0, 50);
        $approvalQueueRepo->addReportsToUnapprovedItems($unapprovedItems);

        $viewParams = [
            'filters' => $filters,
            'count' => $unapprovedItemsSliced->count(),
            'total' => $numUnapprovedItems,
            'hasMore' => $unapprovedItemsSliced->count() < $numUnapprovedItems,
            'last' => $unapprovedItemsSliced->last(),
            'unapprovedItems' => $unapprovedItemsSliced,
        ];
        return $this->view(
            'SV\ReportImprovements:ApprovalQueue\Update',
            'approval_queue',
            $viewParams
        );
    }

    protected function getQueueFilterInputDefinitions()
    {
        $arr = [
            'content_type' => 'str',
            'order' => 'str',
            'direction' => 'str',
            'include_reported' => '?bool',
        ];

        if (Helper::isAddOnActive('NF/Tickets'))
        {
            $arr['without_tickets'] = '?bool';
        }

        return $arr;
    }

    protected function isLoadingDefaultFilters(array $inputDefinitions): bool
    {
        $request = $this->request;

        if ($request->exists('applied_filters'))
        {
            return false;
        }

        foreach ($inputDefinitions as $key => $type)
        {
            if ($request->exists($key))
            {
                return false;
            }
        }

        return true;
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    protected function getQueueFilterInput()
    {
        $filters = [];
        $inputDefinitions = $this->getQueueFilterInputDefinitions();
        $input = $this->filter($inputDefinitions);

        if ($this->isLoadingDefaultFilters($inputDefinitions))
        {
            /** @var ExtendedApprovalQueueRepo $approvalQueueRepo */
            $approvalQueueRepo = Helper::repository(ApprovalQueueRepo::class);

            $savedFilters = $approvalQueueRepo->getUserDefaultFilters(\XF::visitor());
            $input = array_replace($input, $savedFilters);
        }
        else
        {
            $filters['applied_filters'] = true;
        }
        $defaults = \XF::options()->svApprovalQueueFilterDefaults ?? [];

        $filters['include_reported'] = (bool)($input['include_reported'] ?? $defaults['include_reported'] ?? true);

        if (Helper::isAddOnActive('NF/Tickets'))
        {
            $filters['without_tickets'] = (bool)($input['without_tickets'] ?? $defaults['without_tickets'] ?? true);
        }

        if ($input['content_type'])
        {
            $filters['content_type'] = $input['content_type'];
        }

        $sorts = $this->getAvailableQueueSorts();

        if ($input['order'] && isset($sorts[$input['order']]))
        {
            if (!in_array($input['direction'], ['asc', 'desc']))
            {
                $input['direction'] = 'asc';
            }

            if ($input['order'] !== 'content_date' || $input['direction'] !== 'asc')
            {
                $filters['order'] = $input['order'];
                $filters['direction'] = $input['direction'];
            }
        }

        return $filters;
    }

    /**
     * @param Finder|ApprovalQueueFinder $finder
     * @param array                      $filters
     * @return void
     */
    protected function applyQueueFilters(Finder $finder, array $filters)
    {
        parent::applyQueueFilters($finder, $filters);

        // note; defaults here should match XF defaults, not addon defaults

        $includeReported = (bool)($filters['include_reported'] ?? true);
        if (!$includeReported)
        {
            $finder->withoutReport();
        }

        if (Helper::isAddOnActive('NF/Tickets'))
        {
            $includeWithoutTickets = (bool)($filters['without_tickets'] ?? true);
            if (!$includeWithoutTickets)
            {
                $finder->withoutTicket();
            }
        }
    }

    public function actionFilters(ParameterBag $params)
    {
        $result = parent::actionFilters($params);
        if ($result instanceof RedirectReply)
        {
            if ($this->filter('save', 'bool') && $this->isPost())
            {
                $this->request->set('applied_filters', true);
                $filters = $this->getQueueFilterInput();
                unset($filters['applied_filters']);

                /** @var ExtendedApprovalQueueRepo $approvalQueueRepo */
                $approvalQueueRepo = Helper::repository(ApprovalQueueRepo::class);
                $approvalQueueRepo->saveUserDefaultFilters(\XF::visitor(), $filters);
            }
        }

        return $result;
    }

    public function actionReport(): AbstractReply
    {
        $approvalQueueItem = $this->assertViewableApprovalQueueItem(
            $this->filter('content_type', 'str'),
            $this->filter('content_id', 'uint')
        );
        if (!$approvalQueueItem->canReport($error))
        {
            return $this->noPermission($error);
        }

        $reportableContent = $approvalQueueItem->ReportableContent;

        /** @var \XF\ControllerPlugin\Report $reportPlugin */
        $reportPlugin = Helper::plugin($this, \XF\ControllerPlugin\Report::class);
        return $reportPlugin->actionReport(
            $reportableContent->getEntityContentType(), $reportableContent,
            $this->buildLink('approval-queue/report', null, [
                'content_type' => $approvalQueueItem->content_type,
                'content_id' => $approvalQueueItem->content_id,
            ]),
            $this->buildLink('approval-queue')
        );
    }

    /**
     * @param string|null $contentType
     * @param int|null    $contentId
     * @param string[]    $with
     * @return ApprovalQueueEntity|ExtendedApprovalQueueEntity
     * @noinspection PhpDocMissingThrowsInspection
     */
    protected function assertViewableApprovalQueueItem(?string $contentType, ?int $contentId, array $with = []): ApprovalQueueEntity
    {
        /** @var ExtendedApprovalQueueEntity $approvalQueueItem */
        $approvalQueueItem = Helper::findOne(ApprovalQueueEntity::class, [
            'content_type' => $contentType,
            'content_id' => $contentId,
        ], $with);
        if ($approvalQueueItem === null)
        {
            throw $this->exception($this->notFound());
        }

        if ($approvalQueueItem->isInvalid())
        {
            /** @var ApprovalQueueEntity[] $items */
            $items = new ArrayCollection([
                $approvalQueueItem->getIdentifier() => $approvalQueueItem,
            ]);
            $approvalQueueRepo = Helper::repository(ApprovalQueueRepo::class);
            $approvalQueueRepo->cleanUpInvalidRecords($items);

            throw $this->exception($this->notFound());
        }

        $handler = $approvalQueueItem->getHandler();
        if ($handler === null || !$handler->canView($approvalQueueItem->Content))
        {
            throw $this->exception($this->notFound());
        }

        return $approvalQueueItem;
    }
}