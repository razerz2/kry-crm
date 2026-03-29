<?php

namespace Webkul\Admin\DataGrids\Commercial;

use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class CommercialCampaignDeliveryDataGrid extends DataGrid
{
    /**
     * Prepare query builder.
     * Scoped to the campaign ID coming from the route parameter.
     */
    public function prepareQueryBuilder()
    {
        $campaignId = (int) request()->route('id');

        $queryBuilder = DB::table('commercial_campaign_deliveries as d')
            ->where('d.commercial_campaign_id', $campaignId)
            ->addSelect([
                'd.id',
                'd.channel',
                'd.provider',
                'd.recipient_name',
                'd.recipient_email',
                'd.recipient_phone',
                'd.subject',
                'd.status',
                'd.failure_reason',
                'd.queued_at',
                'd.sent_at',
                'd.failed_at',
                'd.created_at',
            ]);

        $this->addFilter('id', 'd.id');
        $this->addFilter('channel', 'd.channel');
        $this->addFilter('provider', 'd.provider');
        $this->addFilter('status', 'd.status');
        $this->addFilter('recipient_name', 'd.recipient_name');
        $this->addFilter('recipient_email', 'd.recipient_email');
        $this->addFilter('sent_at', 'd.sent_at');

        return $queryBuilder;
    }

    public function prepareColumns()
    {
        $this->addColumn([
            'index' => 'id',
            'label' => trans('admin::app.commercial.campaigns.deliveries.datagrid.id'),
            'type' => 'integer',
            'sortable' => true,
            'searchable' => false,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index' => 'recipient_name',
            'label' => trans('admin::app.commercial.campaigns.deliveries.datagrid.recipient'),
            'type' => 'string',
            'sortable' => true,
            'searchable' => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index' => 'recipient_email',
            'label' => trans('admin::app.commercial.campaigns.deliveries.datagrid.email'),
            'type' => 'string',
            'sortable' => true,
            'searchable' => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index' => 'recipient_phone',
            'label' => trans('admin::app.commercial.campaigns.deliveries.datagrid.phone'),
            'type' => 'string',
            'sortable' => false,
            'searchable' => true,
        ]);

        $this->addColumn([
            'index' => 'channel',
            'label' => trans('admin::app.commercial.campaigns.deliveries.datagrid.channel'),
            'type' => 'string',
            'sortable' => true,
            'filterable' => true,
            'closure' => fn ($row) => trans('admin::app.commercial.campaigns.channels.'.$row->channel),
        ]);

        $this->addColumn([
            'index' => 'provider',
            'label' => trans('admin::app.commercial.campaigns.deliveries.datagrid.provider'),
            'type' => 'string',
            'sortable' => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index' => 'status',
            'label' => trans('admin::app.commercial.campaigns.deliveries.datagrid.status'),
            'type' => 'string',
            'sortable' => true,
            'filterable' => true,
            'closure' => fn ($row) => trans('admin::app.commercial.campaigns.deliveries.statuses.'.$row->status),
        ]);

        $this->addColumn([
            'index' => 'subject',
            'label' => trans('admin::app.commercial.campaigns.deliveries.datagrid.subject'),
            'type' => 'string',
            'sortable' => false,
            'closure' => fn ($row) => $row->subject ?: '—',
        ]);

        $this->addColumn([
            'index' => 'failure_reason',
            'label' => trans('admin::app.commercial.campaigns.deliveries.datagrid.failure-reason'),
            'type' => 'string',
            'sortable' => false,
            'closure' => fn ($row) => $row->failure_reason
                ? '<span title="'.e($row->failure_reason).'" class="cursor-help">'
                    .e(mb_substr($row->failure_reason, 0, 60))
                    .(mb_strlen((string) $row->failure_reason) > 60 ? '…' : '')
                    .'</span>'
                : '—',
        ]);

        $this->addColumn([
            'index' => 'sent_at',
            'label' => trans('admin::app.commercial.campaigns.deliveries.datagrid.sent-at'),
            'type' => 'datetime_range',
            'sortable' => true,
            'filterable' => true,
        ]);
    }

    public function prepareActions()
    {
        $campaignId = (int) request()->route('id');

        $this->addAction([
            'index' => 'show',
            'icon' => 'icon-eye',
            'title' => trans('admin::app.commercial.campaigns.deliveries.datagrid.view'),
            'method' => 'GET',
            'url' => fn ($row) => route('admin.commercial.campaigns.delivery_show', [
                'id' => $campaignId,
                'deliveryId' => $row->id,
            ]),
        ]);
    }
}
