<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace SV\ReportImprovements\XF\Pub\Controller;

use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Finder;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Redirect as RedirectReply;

/**
 * @see \XF\Pub\Controller\ApprovalQueue
 */
class ApprovalQueue extends XFCP_ApprovalQueue
{
    /** @noinspection PhpMissingParentCallCommonInspection */
    public function actionIndex()
    {
        $approvalQueueRepo = $this->getApprovalQueueRepo();

        $unapprovedFinder = $approvalQueueRepo->findUnapprovedContent();

        $filters = $this->getQueueFilterInput();
        $this->applyQueueFilters($unapprovedFinder, $filters);

        /** @var \XF\Entity\ApprovalQueue[]|ArrayCollection $unapprovedItems */
        $unapprovedItems = $unapprovedFinder->fetch();
        $numUnapprovedItems = $unapprovedFinder->total();

        /** @noinspection PhpUndefinedFieldInspection */
        if ($numUnapprovedItems != $this->app->unapprovedCounts['total'])
        {
            $approvalQueueRepo->rebuildUnapprovedCounts();
        }

        $approvalQueueRepo->addContentToUnapprovedItems($unapprovedItems);
        $approvalQueueRepo->cleanUpInvalidRecords($unapprovedItems);
        $unapprovedItems = $approvalQueueRepo->filterViewableUnapprovedItems($unapprovedItems);
        $unapprovedItemsSliced = $unapprovedItems->slice(0, 50);

        $viewParams = [
            'filters' => $filters,
            'count' => $unapprovedItemsSliced->count(),
            'total' => $numUnapprovedItems,
            'hasMore' => $unapprovedItemsSliced->count() < $numUnapprovedItems,
            'last' => $unapprovedItemsSliced->last(),
            'unapprovedItems' => $unapprovedItemsSliced,
        ];
        return $this->view('XF:ApprovalQueue\Listing', 'approval_queue', $viewParams);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function actionLoadMore(ParameterBag $params): AbstractReply
    {
        $approvalQueueRepo = $this->getApprovalQueueRepo();

        $unapprovedFinder = $approvalQueueRepo->findUnapprovedContent();
        $unapprovedFinder->where('content_date', '>', $this->filter('last_date', 'uint'));

        $filters = $this->getQueueFilterInput();
        $this->applyQueueFilters($unapprovedFinder, $filters);

        /** @var \XF\Entity\ApprovalQueue[]|ArrayCollection $unapprovedItems */
        $unapprovedItems = $unapprovedFinder->fetch();
        $numUnapprovedItems = $unapprovedItems->count();

        $approvalQueueRepo->addContentToUnapprovedItems($unapprovedItems);
        $approvalQueueRepo->cleanUpInvalidRecords($unapprovedItems);

        $unapprovedItems = $approvalQueueRepo->filterViewableUnapprovedItems($unapprovedItems);
        $unapprovedItemsSliced = $unapprovedItems->slice(0, 50);

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

        if (\XF::isAddOnActive('NF/Tickets'))
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
            /** @var \SV\ReportImprovements\XF\Repository\ApprovalQueue $approvalQueueRepo */
            $approvalQueueRepo = $this->repository('XF:ApprovalQueue');

            $savedFilters = $approvalQueueRepo->getUserDefaultFilters(\XF::visitor());
            $input = array_replace($input, $savedFilters);
        }
        else
        {
            $filters['applied_filters'] = true;
        }
        $defaults = \XF::options()->svApprovalQueueFilterDefaults ?? [];

        $filters['include_reported'] = (bool)($input['include_reported'] ?? $defaults['include_reported'] ?? true);

        if (\XF::isAddOnActive('NF/Tickets'))
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

            if ($input['order'] != 'content_date' || $input['direction'] != 'asc')
            {
                $filters['order'] = $input['order'];
                $filters['direction'] = $input['direction'];
            }
        }

        return $filters;
    }

    /**
     * @param Finder $finder
     * @param array $filters
     * @return void
     */
    protected function applyQueueFilters(Finder $finder, array $filters)
    {
        parent::applyQueueFilters($finder, $filters);

        // note; defaults here should match XF defaults, not addon defaults

        $includeReported = (bool)($filters['include_reported'] ?? true);
        if (!$includeReported)
        {
            $finder->where('Report.report_id', null);
        }

        if (\XF::isAddOnActive('NF/Tickets'))
        {
            $includeWithoutTickets = (bool)($filters['without_tickets'] ?? true);
            if (!$includeWithoutTickets)
            {
                $finder->whereOr(
                    ['content_type', '<>', 'user'],
                    ['User.nf_tickets_count', '<>', 0]
                );
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

                /** @var \SV\ReportImprovements\XF\Repository\ApprovalQueue $approvalQueueRepo */
                $approvalQueueRepo = $this->repository('XF:ApprovalQueue');
                $approvalQueueRepo->saveUserDefaultFilters(\XF::visitor(), $filters);
            }
        }

        return $result;
    }


    public function actionReport(): AbstractReply
    {
        /** @var \SV\ReportImprovements\XF\Entity\ApprovalQueue $approvalQueueItem */
        $approvalQueueItem = $this->em()->findOne('XF:ApprovalQueue', [
            'content_type' => $this->filter('content_type', 'str'),
            'content_id' => $this->filter('content_id', 'uint'),
        ]);
        if (!$approvalQueueItem)
        {
            return $this->notFound();
        }

        if (!$approvalQueueItem->canReport($error))
        {
            return $this->noPermission($error);
        }

        /** @var \XF\ControllerPlugin\Report $reportPlugin */
        $reportPlugin = $this->plugin('XF:Report');
        return $reportPlugin->actionReport(
            $approvalQueueItem->content_type, $approvalQueueItem->Content,
            $this->buildLink('approval-queue/report', null, [
                'content_type' => $approvalQueueItem->content_type,
                'content_id' => $approvalQueueItem->content_id,
            ]),
            $this->buildLink('approval-queue')
        );
    }
}