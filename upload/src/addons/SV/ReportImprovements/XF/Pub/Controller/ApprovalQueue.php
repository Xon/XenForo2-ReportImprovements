<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace SV\ReportImprovements\XF\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

/**
 * Extends \XF\Pub\Controller\ApprovalQueue
 */
class ApprovalQueue extends XFCP_ApprovalQueue
{
    protected function getQueueFilterInputDefinitions()
    {
        return [
            'content_type' => 'str',
            'order' => 'str',
            'direction' => 'str',
            'include_reported' => 'bool',
        ];
    }

    protected function getQueueFilterInput()
    {
        $loadSaved = !$this->request->exists('applied_filters');

        $inputDefinitions = $this->getQueueFilterInputDefinitions();
        foreach ($inputDefinitions as $key => $type)
        {
            if ($this->request->exists($key))
            {
                $loadSaved = false;
                break;
            }
        }

        $filters = [];

        $input = $this->filter($inputDefinitions);

        if ($loadSaved)
        {
            $savedFilters = $this->getApprovalQueueRepo()->getUserDefaultFilters(\XF::visitor());
            $input = array_replace($input, $savedFilters);

            if (isset($savedFilters['include_reported']))
            {
                $this->request->set('include_reported', $savedFilters['include_reported']);
            }
        }
        else
        {
            $filters['applied_filters'] = true;
        }

        if ($input['include_reported'])
        {
            $filters['include_reported'] = $input['include_reported'];
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
     * @param \XF\Mvc\Entity\Finder $finder
     * @param array $filters
     * @return void
     */
    protected function applyQueueFilters(\XF\Mvc\Entity\Finder $finder, array $filters)
    {
        parent::applyQueueFilters($finder, $filters);

        if (!isset($filters['include_reported']) || !$filters['include_reported'])
        {
            $finder->where('Report.report_id', null);
        }
    }


    public function actionFilters(ParameterBag $params)
    {
        $result = parent::actionFilters($params);
        if ($result instanceof \XF\Mvc\Reply\Redirect)
        {
            if ($this->filter('save', 'bool') && $this->isPost())
            {
                $filters = $this->getQueueFilterInput();
                $this->getApprovalQueueRepo()->saveUserDefaultFilters(\XF::visitor(), $filters);
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