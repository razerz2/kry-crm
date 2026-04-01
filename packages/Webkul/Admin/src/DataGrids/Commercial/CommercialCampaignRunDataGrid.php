<?php

namespace Webkul\Admin\DataGrids\Commercial;

use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class CommercialCampaignRunDataGrid extends DataGrid
{
    /**
     * Prepare query builder.
     */
    public function prepareQueryBuilder()
    {
        $queryBuilder = DB::table('commercial_campaign_runs as runs')
            ->join('commercial_campaigns as campaigns', 'campaigns.id', '=', 'runs.commercial_campaign_id')
            ->leftJoin('users as creators', 'creators.id', '=', 'runs.created_by')
            ->addSelect([
                'runs.id',
                'runs.commercial_campaign_id',
                'campaigns.name as campaign_name',
                'runs.trigger_type',
                'runs.scheduled_for',
                'runs.started_at',
                'runs.finished_at',
                'runs.status',
                'runs.total_deliveries',
                'runs.total_sent',
                'runs.total_failed',
                'campaigns.next_run_at',
                'creators.name as created_by_name',
            ]);

        if ($campaignId = (int) request('campaign_id')) {
            $queryBuilder->where('runs.commercial_campaign_id', $campaignId);
        }

        if ($runStatus = request('run_status')) {
            $queryBuilder->where('runs.status', $runStatus);
        }

        if ($triggerType = request('trigger_type')) {
            $queryBuilder->where('runs.trigger_type', $triggerType);
        }

        $this->addFilter('campaign_id', 'runs.commercial_campaign_id');
        $this->addFilter('campaign_name', 'campaigns.name');
        $this->addFilter('trigger_type', 'runs.trigger_type');
        $this->addFilter('status', 'runs.status');
        $this->addFilter('scheduled_for', 'runs.scheduled_for');
        $this->addFilter('started_at', 'runs.started_at');
        $this->addFilter('finished_at', 'runs.finished_at');

        return $queryBuilder;
    }

    /**
     * Add columns.
     */
    public function prepareColumns()
    {
        $this->addColumn([
            'index' => 'id',
            'label' => trans('admin::app.commercial.executions.datagrid.id'),
            'type' => 'integer',
            'sortable' => true,
            'searchable' => true,
        ]);

        $this->addColumn([
            'index' => 'campaign_id',
            'label' => trans('admin::app.commercial.executions.datagrid.campaign-filter'),
            'type' => 'integer',
            'sortable' => false,
            'searchable' => false,
            'filterable' => true,
            'filterable_type' => 'searchable_dropdown',
            'filterable_options' => fn () => $this->campaignFilterOptions(),
            'visibility' => false,
        ]);

        $this->addColumn([
            'index' => 'campaign_name',
            'label' => trans('admin::app.commercial.executions.datagrid.campaign'),
            'type' => 'string',
            'sortable' => true,
            'searchable' => true,
            'filterable' => true,
            'closure' => fn ($row) => '<a href="'.e(route('admin.commercial.campaigns.show', $row->commercial_campaign_id)).'" class="text-brandColor hover:underline">'.e($row->campaign_name).'</a>',
        ]);

        $this->addColumn([
            'index' => 'trigger_type',
            'label' => trans('admin::app.commercial.executions.datagrid.execution-type'),
            'type' => 'string',
            'sortable' => true,
            'filterable' => true,
            'filterable_type' => 'dropdown',
            'filterable_options' => [
                ['label' => trans('admin::app.commercial.executions.trigger-types.scheduler'), 'value' => 'scheduler'],
                ['label' => trans('admin::app.commercial.executions.trigger-types.manual'), 'value' => 'manual'],
            ],
            'closure' => fn ($row) => trans('admin::app.commercial.executions.trigger-types.'.$row->trigger_type),
        ]);

        $this->addColumn([
            'index' => 'scheduled_for',
            'label' => trans('admin::app.commercial.executions.datagrid.scheduled-for'),
            'type' => 'datetime',
            'sortable' => true,
            'filterable' => true,
            'filterable_type' => 'datetime_range',
        ]);

        $this->addColumn([
            'index' => 'started_at',
            'label' => trans('admin::app.commercial.executions.datagrid.started-at'),
            'type' => 'datetime',
            'sortable' => true,
            'filterable' => true,
            'filterable_type' => 'datetime_range',
        ]);

        $this->addColumn([
            'index' => 'finished_at',
            'label' => trans('admin::app.commercial.executions.datagrid.finished-at'),
            'type' => 'datetime',
            'sortable' => true,
            'filterable' => true,
            'filterable_type' => 'datetime_range',
        ]);

        $this->addColumn([
            'index' => 'status',
            'label' => trans('admin::app.commercial.executions.datagrid.status'),
            'type' => 'string',
            'sortable' => true,
            'filterable' => true,
            'filterable_type' => 'dropdown',
            'filterable_options' => [
                ['label' => trans('admin::app.commercial.campaigns.schedule.run-statuses.queued'), 'value' => 'queued'],
                ['label' => trans('admin::app.commercial.campaigns.schedule.run-statuses.running'), 'value' => 'running'],
                ['label' => trans('admin::app.commercial.campaigns.schedule.run-statuses.completed'), 'value' => 'completed'],
                ['label' => trans('admin::app.commercial.campaigns.schedule.run-statuses.failed'), 'value' => 'failed'],
                ['label' => trans('admin::app.commercial.campaigns.schedule.run-statuses.canceled'), 'value' => 'canceled'],
            ],
            'closure' => fn ($row) => $this->statusBadge($row->status),
        ]);

        $this->addColumn([
            'index' => 'total_deliveries',
            'label' => trans('admin::app.commercial.executions.datagrid.total-deliveries'),
            'type' => 'integer',
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'total_sent',
            'label' => trans('admin::app.commercial.executions.datagrid.sent'),
            'type' => 'integer',
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'total_failed',
            'label' => trans('admin::app.commercial.executions.datagrid.failed'),
            'type' => 'integer',
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'next_run_at',
            'label' => trans('admin::app.commercial.executions.datagrid.next-run-at'),
            'type' => 'datetime',
            'sortable' => true,
            'filterable' => true,
            'filterable_type' => 'datetime_range',
        ]);
    }

    /**
     * Prepare actions.
     */
    public function prepareActions()
    {
        if (bouncer()->hasPermission('commercial.executions.view')) {
            $this->addAction([
                'index' => 'view',
                'icon' => 'icon-eye',
                'title' => trans('admin::app.commercial.executions.datagrid.view'),
                'method' => 'GET',
                'url' => fn ($row) => route('admin.commercial.executions.show', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('commercial.deliveries')) {
            $this->addAction([
                'index' => 'deliveries',
                'icon' => 'icon-note',
                'title' => trans('admin::app.commercial.executions.datagrid.audit-deliveries'),
                'method' => 'GET',
                'url' => fn ($row) => route('admin.commercial.deliveries.index', [
                    'campaign_id' => $row->commercial_campaign_id,
                    'run_id' => $row->id,
                ]),
            ]);
        }
    }

    /**
     * @return array<int, array{label:string, value:int}>
     */
    protected function campaignFilterOptions(): array
    {
        return DB::table('commercial_campaigns')
            ->select('id as value', 'name as label')
            ->orderBy('name')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->label,
                'value' => (int) $row->value,
            ])
            ->values()
            ->all();
    }

    protected function statusBadge(string $status): string
    {
        $class = match ($status) {
            'completed' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            'failed' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
            'running' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
            'canceled' => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
            default => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        };

        return '<span class="rounded-full px-2 py-0.5 text-xs font-medium '.$class.'">'
            .e(trans('admin::app.commercial.campaigns.schedule.run-statuses.'.$status))
            .'</span>';
    }
}
