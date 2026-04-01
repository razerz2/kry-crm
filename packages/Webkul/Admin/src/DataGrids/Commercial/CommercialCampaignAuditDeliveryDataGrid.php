<?php

namespace Webkul\Admin\DataGrids\Commercial;

use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class CommercialCampaignAuditDeliveryDataGrid extends DataGrid
{
    /**
     * Prepare query builder.
     */
    public function prepareQueryBuilder()
    {
        $queryBuilder = DB::table('commercial_campaign_deliveries as d')
            ->leftJoin('commercial_campaigns as c', 'c.id', '=', 'd.commercial_campaign_id')
            ->leftJoin('commercial_campaign_runs as r', 'r.id', '=', 'd.commercial_campaign_run_id')
            ->addSelect([
                'd.id',
                'd.commercial_campaign_id',
                'd.commercial_campaign_run_id',
                'd.entity_type',
                'd.entity_id',
                'd.channel',
                'd.provider',
                'd.recipient_name',
                'd.recipient_email',
                'd.recipient_phone',
                'd.normalized_destination',
                'd.status',
                'd.sent_at',
                'd.failed_at',
                'd.failure_reason',
                'd.provider_message_id',
                'd.created_at',
                'c.name as campaign_name',
                DB::raw('CASE WHEN LOWER(d.entity_type) LIKE \'%organization%\' THEN \'organization\' ELSE \'person\' END as entity_scope'),
                DB::raw('CASE WHEN d.status = \'failed\' THEN 1 ELSE 0 END as only_failures'),
            ]);

        if ($campaignId = (int) request('campaign_id')) {
            $queryBuilder->where('d.commercial_campaign_id', $campaignId);
        }

        if ($runId = (int) request('run_id')) {
            $queryBuilder->where('d.commercial_campaign_run_id', $runId);
        }

        if (request()->boolean('only_failures')) {
            $queryBuilder->where('d.status', 'failed');
        }

        $this->addFilter('campaign_id', 'd.commercial_campaign_id');
        $this->addFilter('campaign_name', 'c.name');
        $this->addFilter('run_id', 'd.commercial_campaign_run_id');
        $this->addFilter('entity_type', DB::raw('CASE WHEN LOWER(d.entity_type) LIKE \'%organization%\' THEN \'organization\' ELSE \'person\' END'));
        $this->addFilter('recipient_name', 'd.recipient_name');
        $this->addFilter('channel', 'd.channel');
        $this->addFilter('status', 'd.status');
        $this->addFilter('destination_used', 'd.normalized_destination');
        $this->addFilter('normalized_destination', 'd.normalized_destination');
        $this->addFilter('failure_reason', 'd.failure_reason');
        $this->addFilter('provider_message_id', 'd.provider_message_id');
        $this->addFilter('sent_at', 'd.sent_at');
        $this->addFilter('failed_at', 'd.failed_at');
        $this->addFilter('created_at', 'd.created_at');
        $this->addFilter('only_failures', DB::raw('CASE WHEN d.status = \'failed\' THEN 1 ELSE 0 END'));

        return $queryBuilder;
    }

    /**
     * Add columns.
     */
    public function prepareColumns()
    {
        $this->addColumn([
            'index' => 'id',
            'label' => trans('admin::app.commercial.delivery-audit.datagrid.id'),
            'type' => 'integer',
            'sortable' => true,
            'searchable' => true,
        ]);

        $this->addColumn([
            'index' => 'campaign_id',
            'label' => trans('admin::app.commercial.delivery-audit.datagrid.campaign-filter'),
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
            'label' => trans('admin::app.commercial.delivery-audit.datagrid.campaign'),
            'type' => 'string',
            'sortable' => true,
            'searchable' => true,
            'filterable' => true,
            'closure' => fn ($row) => $row->commercial_campaign_id
                ? '<a href="'.e(route('admin.commercial.campaigns.show', $row->commercial_campaign_id)).'" class="text-brandColor hover:underline">'.e((string) $row->campaign_name).'</a>'
                : '-',
        ]);

        $this->addColumn([
            'index' => 'run_id',
            'label' => trans('admin::app.commercial.delivery-audit.datagrid.run'),
            'type' => 'integer',
            'sortable' => true,
            'filterable' => true,
            'filterable_type' => 'searchable_dropdown',
            'filterable_options' => fn () => $this->runFilterOptions(),
            'allow_multiple_values' => false,
            'closure' => fn ($row) => $row->commercial_campaign_run_id
                ? '<a href="'.e(route('admin.commercial.executions.show', $row->commercial_campaign_run_id)).'" class="text-brandColor hover:underline">#'.e((string) $row->commercial_campaign_run_id).'</a>'
                : '-',
        ]);

        $this->addColumn([
            'index' => 'recipient_name',
            'label' => trans('admin::app.commercial.delivery-audit.datagrid.entity'),
            'type' => 'string',
            'sortable' => true,
            'searchable' => true,
            'filterable' => true,
            'closure' => fn ($row) => e($row->recipient_name ?: '-')
                .' <span class="text-xs text-gray-400">('.e($this->entityLabel((string) $row->entity_scope)).')</span>',
        ]);

        $this->addColumn([
            'index' => 'entity_type',
            'label' => trans('admin::app.commercial.delivery-audit.datagrid.entity-type'),
            'type' => 'string',
            'sortable' => false,
            'filterable' => true,
            'filterable_type' => 'dropdown',
            'filterable_options' => [
                ['label' => trans('admin::app.commercial.delivery-audit.entity-types.person'), 'value' => 'person'],
                ['label' => trans('admin::app.commercial.delivery-audit.entity-types.organization'), 'value' => 'organization'],
            ],
            'visibility' => false,
            'closure' => fn ($row) => $this->entityLabel((string) $row->entity_scope),
        ]);

        $this->addColumn([
            'index' => 'channel',
            'label' => trans('admin::app.commercial.delivery-audit.datagrid.channel'),
            'type' => 'string',
            'sortable' => true,
            'filterable' => true,
            'filterable_type' => 'dropdown',
            'filterable_options' => [
                ['label' => trans('admin::app.commercial.campaigns.channels.email'), 'value' => 'email'],
                ['label' => trans('admin::app.commercial.campaigns.channels.whatsapp'), 'value' => 'whatsapp'],
            ],
            'closure' => fn ($row) => trans('admin::app.commercial.campaigns.channels.'.$row->channel),
        ]);

        $this->addColumn([
            'index' => 'destination_used',
            'label' => trans('admin::app.commercial.delivery-audit.datagrid.destination'),
            'type' => 'string',
            'sortable' => false,
            'searchable' => true,
            'filterable' => true,
            'closure' => fn ($row) => e($this->destinationFromRow($row)),
        ]);

        $this->addColumn([
            'index' => 'normalized_destination',
            'label' => trans('admin::app.commercial.delivery-audit.datagrid.normalized-destination'),
            'type' => 'string',
            'sortable' => false,
            'searchable' => true,
            'filterable' => true,
            'closure' => fn ($row) => e($row->normalized_destination ?: '-'),
        ]);

        $this->addColumn([
            'index' => 'status',
            'label' => trans('admin::app.commercial.delivery-audit.datagrid.status'),
            'type' => 'string',
            'sortable' => true,
            'filterable' => true,
            'filterable_type' => 'dropdown',
            'filterable_options' => $this->statusFilterOptions(),
            'closure' => fn ($row) => $this->statusBadge((string) $row->status),
        ]);

        $this->addColumn([
            'index' => 'sent_at',
            'label' => trans('admin::app.commercial.delivery-audit.datagrid.sent-at'),
            'type' => 'datetime',
            'sortable' => true,
            'filterable' => true,
            'filterable_type' => 'datetime_range',
        ]);

        $this->addColumn([
            'index' => 'failed_at',
            'label' => trans('admin::app.commercial.delivery-audit.datagrid.failed-at'),
            'type' => 'datetime',
            'sortable' => true,
            'filterable' => true,
            'filterable_type' => 'datetime_range',
        ]);

        $this->addColumn([
            'index' => 'failure_reason',
            'label' => trans('admin::app.commercial.delivery-audit.datagrid.error'),
            'type' => 'string',
            'sortable' => false,
            'searchable' => true,
            'filterable' => true,
            'closure' => fn ($row) => $row->failure_reason
                ? '<span title="'.e($row->failure_reason).'" class="cursor-help">'
                    .e(mb_substr($row->failure_reason, 0, 80))
                    .(mb_strlen((string) $row->failure_reason) > 80 ? '...' : '')
                    .'</span>'
                : '-',
        ]);

        $this->addColumn([
            'index' => 'provider_message_id',
            'label' => trans('admin::app.commercial.delivery-audit.datagrid.provider-message-id'),
            'type' => 'string',
            'sortable' => false,
            'searchable' => true,
            'filterable' => true,
            'closure' => fn ($row) => e($row->provider_message_id ?: '-'),
        ]);

        $this->addColumn([
            'index' => 'only_failures',
            'label' => trans('admin::app.commercial.delivery-audit.datagrid.only-failures'),
            'type' => 'boolean',
            'sortable' => false,
            'filterable' => true,
            'filterable_type' => 'dropdown',
            'allow_multiple_values' => false,
            'visibility' => false,
        ]);
    }

    /**
     * Prepare actions.
     */
    public function prepareActions()
    {
        if (bouncer()->hasPermission('commercial.deliveries.view')) {
            $this->addAction([
                'index' => 'view',
                'icon' => 'icon-eye',
                'title' => trans('admin::app.commercial.delivery-audit.datagrid.view'),
                'method' => 'GET',
                'url' => fn ($row) => route('admin.commercial.deliveries.show', $row->id),
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

    /**
     * @return array<int, array{label:string, value:int}>
     */
    protected function runFilterOptions(): array
    {
        return DB::table('commercial_campaign_runs as runs')
            ->leftJoin('commercial_campaigns as campaigns', 'campaigns.id', '=', 'runs.commercial_campaign_id')
            ->select('runs.id as value', 'campaigns.name as campaign_name')
            ->orderByDesc('runs.id')
            ->limit(300)
            ->get()
            ->map(fn ($row) => [
                'label' => '#'.$row->value.' - '.($row->campaign_name ?: trans('admin::app.commercial.delivery-audit.labels.no-campaign')),
                'value' => (int) $row->value,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label:string, value:string}>
     */
    protected function statusFilterOptions(): array
    {
        return [
            ['label' => trans('admin::app.commercial.campaigns.deliveries.statuses.pending'), 'value' => 'pending'],
            ['label' => trans('admin::app.commercial.campaigns.deliveries.statuses.queued'), 'value' => 'queued'],
            ['label' => trans('admin::app.commercial.campaigns.deliveries.statuses.sending'), 'value' => 'sending'],
            ['label' => trans('admin::app.commercial.campaigns.deliveries.statuses.sent'), 'value' => 'sent'],
            ['label' => trans('admin::app.commercial.campaigns.deliveries.statuses.failed'), 'value' => 'failed'],
            ['label' => trans('admin::app.commercial.campaigns.deliveries.statuses.skipped'), 'value' => 'skipped'],
            ['label' => trans('admin::app.commercial.campaigns.deliveries.statuses.canceled'), 'value' => 'canceled'],
            ['label' => trans('admin::app.commercial.delivery-audit.statuses.deduplicated'), 'value' => 'deduplicated'],
            ['label' => trans('admin::app.commercial.delivery-audit.statuses.ignored'), 'value' => 'ignored'],
        ];
    }

    protected function entityLabel(string $entityType): string
    {
        if (str_contains(strtolower($entityType), 'organization')) {
            return trans('admin::app.commercial.delivery-audit.entity-types.organization');
        }

        return trans('admin::app.commercial.delivery-audit.entity-types.person');
    }

    protected function destinationFromRow(object $row): string
    {
        if (! empty($row->recipient_email)) {
            return (string) $row->recipient_email;
        }

        if (! empty($row->recipient_phone)) {
            return (string) $row->recipient_phone;
        }

        return (string) ($row->normalized_destination ?: '-');
    }

    protected function statusBadge(string $status): string
    {
        $class = match ($status) {
            'sent' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            'failed' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
            'pending', 'queued', 'sending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
            'deduplicated', 'ignored', 'skipped', 'canceled' => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
            default => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        };

        return '<span class="rounded-full px-2 py-0.5 text-xs font-medium '.$class.'">'
            .e($this->statusLabel($status))
            .'</span>';
    }

    protected function statusLabel(string $status): string
    {
        $deliveryStatusKey = 'admin::app.commercial.campaigns.deliveries.statuses.'.$status;
        $translated = trans($deliveryStatusKey);

        if ($translated !== $deliveryStatusKey) {
            return $translated;
        }

        return trans('admin::app.commercial.delivery-audit.statuses.'.$status);
    }
}
