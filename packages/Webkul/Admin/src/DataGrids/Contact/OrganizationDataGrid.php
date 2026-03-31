<?php

namespace Webkul\Admin\DataGrids\Contact;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Admin\DataGrids\Traits\CommercialDataGridTrait;
use Webkul\Contact\Models\OrganizationProxy;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\DataGrid\DataGrid;

class OrganizationDataGrid extends DataGrid
{
    use CommercialDataGridTrait;

    /**
     * Whether organization JSON contact columns are available.
     */
    protected bool $hasContactColumns = false;

    /**
     * Primary table for this DataGrid.
     */
    protected string $entityTable = 'organizations';

    /**
     * Create datagrid instance.
     *
     * @return void
     */
    public function __construct(protected PersonRepository $personRepository)
    {
        $this->entityMorphClass = OrganizationProxy::modelClass();

        $this->hasContactColumns = Schema::hasColumn('organizations', 'emails')
            && Schema::hasColumn('organizations', 'contact_numbers');
    }

    /**
     * Prepare query builder.
     */
    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('organizations')
            ->addSelect(
                'organizations.id',
                'organizations.name',
                'organizations.cnpj',
                'organizations.address',
                'organizations.created_at'
            );

        if ($this->hasContactColumns) {
            $queryBuilder->addSelect(
                'organizations.emails',
                'organizations.contact_numbers'
            );
        } else {
            $queryBuilder->addSelect(
                DB::raw('NULL as emails'),
                DB::raw('NULL as contact_numbers')
            );
        }

        $commercialGroupByColumns = [
            'organizations.name',
            'organizations.cnpj',
            'organizations.address',
            'organizations.created_at',
        ];

        if ($this->hasContactColumns) {
            $commercialGroupByColumns[] = 'organizations.emails';
            $commercialGroupByColumns[] = 'organizations.contact_numbers';
        }

        $this->applyCommercialJoins($queryBuilder, $commercialGroupByColumns);

        if ($userIds = bouncer()->getAuthorizedUserIds()) {
            $queryBuilder->whereIn('organizations.user_id', $userIds);
        }

        $this->addFilter('id', 'organizations.id');
        $this->addFilter('name', 'organizations.name');

        return $queryBuilder;
    }

    /**
     * Add columns.
     */
    public function prepareColumns(): void
    {
        $this->addColumn([
            'index' => 'id',
            'label' => trans('admin::app.contacts.organizations.index.datagrid.id'),
            'type' => 'integer',
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'name',
            'label' => trans('admin::app.contacts.organizations.index.datagrid.name'),
            'type' => 'string',
            'searchable' => true,
            'sortable' => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index' => 'cnpj',
            'label' => 'CNPJ',
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => false,
            'closure' => fn ($row) => $row->cnpj ?? '-',
        ]);

        $this->addColumn([
            'index' => 'emails',
            'label' => trans('admin::app.contacts.persons.index.datagrid.emails'),
            'type' => 'string',
            'searchable' => $this->hasContactColumns,
            'filterable' => $this->hasContactColumns,
            'sortable' => false,
            'closure' => fn ($row) => $this->getPrimaryContactValue($row->emails),
        ]);

        $this->addColumn([
            'index' => 'contact_numbers',
            'label' => trans('admin::app.contacts.persons.index.datagrid.contact-numbers'),
            'type' => 'string',
            'searchable' => $this->hasContactColumns,
            'filterable' => $this->hasContactColumns,
            'sortable' => false,
            'closure' => fn ($row) => $this->getPrimaryContactValue($row->contact_numbers),
        ]);

        $this->addColumn([
            'index' => 'persons_count',
            'label' => trans('admin::app.contacts.organizations.index.datagrid.persons-count'),
            'type' => 'string',
            'searchable' => false,
            'sortable' => false,
            'filterable' => false,
            'closure' => function ($row) {
                $personsCount = $this->personRepository->findWhere(['organization_id' => $row->id])->count();

                return $personsCount;
            },
        ]);

        $this->addCommercialColumns();

        $this->addColumn([
            'index' => 'created_at',
            'label' => trans('admin::app.settings.tags.index.datagrid.created-at'),
            'type' => 'date',
            'searchable' => true,
            'filterable' => true,
            'filterable_type' => 'date_range',
            'sortable' => true,
            'closure' => fn ($row) => core()->formatDate($row->created_at),
        ]);
    }

    /**
     * Prepare actions.
     */
    public function prepareActions(): void
    {
        if (bouncer()->hasPermission('contacts.organizations.view')) {
            $this->addAction([
                'icon' => 'icon-eye',
                'title' => trans('admin::app.contacts.organizations.index.datagrid.view'),
                'method' => 'GET',
                'url' => fn ($row) => route('admin.contacts.organizations.view', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('contacts.organizations.edit')) {
            $this->addAction([
                'icon' => 'icon-edit',
                'title' => trans('admin::app.contacts.organizations.index.datagrid.edit'),
                'method' => 'GET',
                'url' => fn ($row) => route('admin.contacts.organizations.edit', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('contacts.organizations.delete')) {
            $this->addAction([
                'icon' => 'icon-delete',
                'title' => trans('admin::app.contacts.organizations.index.datagrid.delete'),
                'method' => 'DELETE',
                'url' => fn ($row) => route('admin.contacts.organizations.delete', $row->id),
            ]);
        }
    }

    /**
     * Prepare mass actions.
     */
    public function prepareMassActions(): void
    {
        $this->addMassAction([
            'icon' => 'icon-delete',
            'title' => trans('admin::app.contacts.organizations.index.datagrid.delete'),
            'method' => 'PUT',
            'url' => route('admin.contacts.organizations.mass_delete'),
        ]);
    }

    /**
     * Return the first non-empty contact value.
     */
    protected function getPrimaryContactValue(mixed $rawEntries): string
    {
        return $this->extractContactValues($rawEntries)->first() ?? '-';
    }

    /**
     * Normalize JSON contact entries to a flat value list.
     */
    protected function extractContactValues(mixed $rawEntries): Collection
    {
        if (is_string($rawEntries)) {
            $rawEntries = json_decode($rawEntries, true);
        }

        if (! is_array($rawEntries)) {
            return collect();
        }

        return collect($rawEntries)
            ->map(function ($entry) {
                if (is_string($entry)) {
                    return trim($entry);
                }

                if (is_array($entry)) {
                    return trim((string) ($entry['value'] ?? ''));
                }

                return null;
            })
            ->filter();
    }
}
