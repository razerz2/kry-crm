<?php

namespace Webkul\Admin\DataGrids\Commercial;

use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class CommercialCampaignDataGrid extends DataGrid
{
    /**
     * Prepare query builder.
     */
    public function prepareQueryBuilder()
    {
        $queryBuilder = DB::table('commercial_campaigns')
            ->leftJoin('users as creators', 'creators.id', '=', 'commercial_campaigns.created_by')
            ->addSelect(
                'commercial_campaigns.id',
                'commercial_campaigns.name',
                'commercial_campaigns.channel',
                'commercial_campaigns.status',
                'commercial_campaigns.total_audience',
                'commercial_campaigns.audience_generated_at',
                'creators.name as created_by_name',
                'commercial_campaigns.created_at',
            );

        $this->addFilter('id', 'commercial_campaigns.id');
        $this->addFilter('name', 'commercial_campaigns.name');
        $this->addFilter('channel', 'commercial_campaigns.channel');
        $this->addFilter('status', 'commercial_campaigns.status');
        $this->addFilter('created_at', 'commercial_campaigns.created_at');

        return $queryBuilder;
    }

    /**
     * Add columns.
     */
    public function prepareColumns()
    {
        $this->addColumn([
            'index'      => 'id',
            'label'      => trans('admin::app.commercial.campaigns.index.datagrid.id'),
            'type'       => 'integer',
            'sortable'   => true,
            'searchable' => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index'      => 'name',
            'label'      => trans('admin::app.commercial.campaigns.index.datagrid.name'),
            'type'       => 'string',
            'sortable'   => true,
            'searchable' => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index'    => 'channel',
            'label'    => trans('admin::app.commercial.campaigns.index.datagrid.channel'),
            'type'     => 'string',
            'sortable' => true,
            'closure'  => fn ($row) => trans('admin::app.commercial.campaigns.channels.' . $row->channel),
        ]);

        $this->addColumn([
            'index'    => 'status',
            'label'    => trans('admin::app.commercial.campaigns.index.datagrid.status'),
            'type'     => 'string',
            'sortable' => true,
            'closure'  => fn ($row) => trans('admin::app.commercial.campaigns.statuses.' . $row->status),
        ]);

        $this->addColumn([
            'index'    => 'total_audience',
            'label'    => trans('admin::app.commercial.campaigns.index.datagrid.audience'),
            'type'     => 'integer',
            'sortable' => true,
        ]);

        $this->addColumn([
            'index'      => 'created_by_name',
            'label'      => trans('admin::app.commercial.campaigns.index.datagrid.created-by'),
            'type'       => 'string',
            'sortable'   => false,
        ]);

        $this->addColumn([
            'index'      => 'created_at',
            'label'      => trans('admin::app.commercial.campaigns.index.datagrid.created-at'),
            'type'       => 'date_range',
            'sortable'   => true,
            'filterable' => true,
        ]);
    }

    /**
     * Prepare actions.
     */
    public function prepareActions()
    {
        if (bouncer()->hasPermission('commercial.campaigns.view')) {
            $this->addAction([
                'index'  => 'view',
                'icon'   => 'icon-eye',
                'title'  => trans('admin::app.commercial.campaigns.index.datagrid.view'),
                'method' => 'GET',
                'url'    => fn ($row) => route('admin.commercial.campaigns.show', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('commercial.campaigns.edit')) {
            $this->addAction([
                'index'  => 'edit',
                'icon'   => 'icon-edit',
                'title'  => trans('admin::app.commercial.campaigns.index.datagrid.edit'),
                'method' => 'GET',
                'url'    => fn ($row) => route('admin.commercial.campaigns.edit', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('commercial.campaigns.delete')) {
            $this->addAction([
                'index'  => 'delete',
                'icon'   => 'icon-delete',
                'title'  => trans('admin::app.commercial.campaigns.index.datagrid.delete'),
                'method' => 'DELETE',
                'url'    => fn ($row) => route('admin.commercial.campaigns.destroy', $row->id),
            ]);
        }
    }

    /**
     * Prepare mass actions.
     */
    public function prepareMassActions(): void
    {
        if (bouncer()->hasPermission('commercial.campaigns.delete')) {
            $this->addMassAction([
                'icon'   => 'icon-delete',
                'title'  => trans('admin::app.commercial.campaigns.index.datagrid.delete'),
                'method' => 'POST',
                'url'    => route('admin.commercial.campaigns.mass_destroy'),
            ]);
        }
    }
}
