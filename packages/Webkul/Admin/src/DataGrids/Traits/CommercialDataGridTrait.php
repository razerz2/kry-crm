<?php

namespace Webkul\Admin\DataGrids\Traits;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\Commercial\Enums\AccountProductStatus;

/**
 * Reusable trait for adding commercial filtering, columns and badges
 * to Person and Organization DataGrids.
 *
 * Strategy:
 * - LEFT JOIN account_products (ap) + crm_products (cp) onto the main table
 * - GROUP BY primary table columns to avoid row duplication
 * - Use GROUP_CONCAT for display columns (badges)
 * - Use HAVING or EXISTS subqueries for dropdown filters on product/status
 * - Provide pre-built "quick segment" dropdown filter (customer, non-customer, etc.)
 *
 * Why not just filter on GROUP_CONCAT alias?
 *   MySQL (and the DataGrid's Text::processFilter) applies WHERE on real column names.
 *   Aliases from SELECT are not available in WHERE. We solve this by:
 *   1. Using addFilter() to point filterable columns to real table columns (ap.status, cp.name)
 *   2. Overriding processRequestedFilters() to intercept our custom filter keys
 *      and apply EXISTS subqueries instead of plain WHERE — this avoids GROUP BY conflicts.
 */
trait CommercialDataGridTrait
{
    /**
     * The morph class for the entity (Person or Organization).
     * Must be set by the DataGrid that uses this trait.
     */
    protected string $entityMorphClass;

    /**
     * The primary table name ('persons' or 'organizations').
     */
    protected string $entityTable;

    /**
     * Apply commercial LEFT JOINs and aggregate SELECT columns to the query builder.
     * Call this inside prepareQueryBuilder() after the base query is built.
     *
     * @param  array  $additionalGroupBy  Extra columns to include in GROUP BY (for strict SQL mode).
     */
    protected function applyCommercialJoins(Builder $queryBuilder, array $additionalGroupBy = []): Builder
    {
        $entityMorphClass = addslashes($this->entityMorphClass);

        $queryBuilder
            ->leftJoin('account_products as ap', function ($join) use ($entityMorphClass) {
                $join->on('ap.entity_id', '=', $this->entityTable.'.id')
                    ->where('ap.entity_type', '=', $entityMorphClass);
            })
            ->leftJoin('crm_products as cp', 'cp.id', '=', 'ap.crm_product_id');

        $queryBuilder->addSelect(
            DB::raw('COUNT(DISTINCT ap.id) as commercial_links_count'),
            DB::raw("GROUP_CONCAT(DISTINCT ap.status ORDER BY ap.status SEPARATOR ', ') as commercial_status"),
            DB::raw("GROUP_CONCAT(DISTINCT cp.name ORDER BY cp.name SEPARATOR ', ') as commercial_products")
        );

        $groupByColumns = array_merge(
            [$this->entityTable.'.id'],
            $additionalGroupBy
        );

        $queryBuilder->groupBy($groupByColumns);

        return $queryBuilder;
    }

    /**
     * Register commercial filter mappings.
     * Called inside prepareQueryBuilder() after applyCommercialJoins().
     */
    protected function addCommercialFilters(): void
    {
        // These filters will be intercepted by processRequestedFilters override
        // No addFilter() call needed — we handle them manually via EXISTS subqueries
    }

    /**
     * Add commercial columns to the DataGrid.
     * Call inside prepareColumns().
     */
    protected function addCommercialColumns(): void
    {
        $this->addColumn([
            'index' => 'commercial_product',
            'label' => 'Produto',
            'type' => 'string',
            'searchable' => false,
            'filterable' => true,
            'sortable' => false,
            'filterable_type' => 'dropdown',
            'filterable_options' => $this->getProductFilterOptions(),
            'closure' => function ($row) {
                if (empty($row->commercial_products)) {
                    return '<span class="text-xs text-gray-400">—</span>';
                }

                return collect(explode(', ', $row->commercial_products))
                    ->map(fn ($name) => '<span class="inline-block rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-300">'.e(trim($name)).'</span>')
                    ->join(' ');
            },
        ]);

        $this->addColumn([
            'index' => 'commercial_status',
            'label' => 'Status Comercial',
            'type' => 'string',
            'searchable' => false,
            'filterable' => true,
            'sortable' => false,
            'filterable_type' => 'dropdown',
            'filterable_options' => $this->getStatusFilterOptions(),
            'closure' => function ($row) {
                return $this->renderStatusBadges($row->commercial_status);
            },
        ]);

        $this->addColumn([
            'index' => 'commercial_links_count',
            'label' => 'Vínculos',
            'type' => 'string',
            'searchable' => false,
            'filterable' => false,
            'sortable' => true,
            'closure' => function ($row) {
                $count = (int) $row->commercial_links_count;

                if ($count === 0) {
                    return '<span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-400">0</span>';
                }

                return '<span class="rounded-full bg-brandColor/10 px-2 py-0.5 text-xs font-semibold text-brandColor">'.$count.'</span>';
            },
        ]);

        $this->addColumn([
            'index' => 'commercial_segment',
            'label' => 'Segmento',
            'type' => 'string',
            'searchable' => false,
            'filterable' => true,
            'sortable' => false,
            'visibility' => false,
            'filterable_type' => 'dropdown',
            'filterable_options' => $this->getSegmentFilterOptions(),
            'closure' => fn ($row) => '',
        ]);
    }

    /**
     * Override processRequestedFilters to intercept commercial filter keys
     * and apply EXISTS subqueries instead of plain WHERE.
     */
    protected function processRequestedFilters(array $requestedFilters)
    {
        $commercialKeys = ['commercial_product', 'commercial_status', 'commercial_segment'];

        $commercialFilters = [];

        foreach ($commercialKeys as $key) {
            if (isset($requestedFilters[$key])) {
                $commercialFilters[$key] = $requestedFilters[$key];
                unset($requestedFilters[$key]);
            }
        }

        // Let the parent handle all standard filters
        parent::processRequestedFilters($requestedFilters);

        // Now apply commercial filters via EXISTS subqueries
        foreach ($commercialFilters as $filterKey => $filterValues) {
            $this->applyCommercialFilter($filterKey, $filterValues);
        }

        return $this->queryBuilder;
    }

    /**
     * Apply a single commercial filter using EXISTS subquery.
     */
    protected function applyCommercialFilter(string $filterKey, mixed $filterValues): void
    {
        $values = is_array($filterValues) ? $filterValues : [$filterValues];
        $entityMorphClass = $this->entityMorphClass;
        $entityTable = $this->entityTable;

        switch ($filterKey) {
            case 'commercial_product':
                $this->queryBuilder->where(function ($query) use ($values, $entityMorphClass, $entityTable) {
                    $query->whereExists(function ($sub) use ($values, $entityMorphClass, $entityTable) {
                        $sub->select(DB::raw(1))
                            ->from('account_products as ap_filter')
                            ->join('crm_products as cp_filter', 'cp_filter.id', '=', 'ap_filter.crm_product_id')
                            ->whereColumn('ap_filter.entity_id', $entityTable.'.id')
                            ->where('ap_filter.entity_type', $entityMorphClass)
                            ->whereIn('cp_filter.name', $values);
                    });
                });
                break;

            case 'commercial_status':
                $this->queryBuilder->where(function ($query) use ($values, $entityMorphClass, $entityTable) {
                    $query->whereExists(function ($sub) use ($values, $entityMorphClass, $entityTable) {
                        $sub->select(DB::raw(1))
                            ->from('account_products as ap_filter')
                            ->whereColumn('ap_filter.entity_id', $entityTable.'.id')
                            ->where('ap_filter.entity_type', $entityMorphClass)
                            ->whereIn('ap_filter.status', $values);
                    });
                });
                break;

            case 'commercial_segment':
                $this->applySegmentFilter($values);
                break;
        }
    }

    /**
     * Apply quick segment filters (customer_any, non_customer, has_link, no_link).
     */
    protected function applySegmentFilter(array $values): void
    {
        $entityMorphClass = $this->entityMorphClass;
        $entityTable = $this->entityTable;

        foreach ($values as $segment) {
            switch ($segment) {
                case 'customer_any':
                    $this->queryBuilder->whereExists(function ($sub) use ($entityMorphClass, $entityTable) {
                        $sub->select(DB::raw(1))
                            ->from('account_products as ap_seg')
                            ->whereColumn('ap_seg.entity_id', $entityTable.'.id')
                            ->where('ap_seg.entity_type', $entityMorphClass)
                            ->where('ap_seg.status', 'customer');
                    });
                    break;

                case 'non_customer':
                    $this->queryBuilder->whereNotExists(function ($sub) use ($entityMorphClass, $entityTable) {
                        $sub->select(DB::raw(1))
                            ->from('account_products as ap_seg')
                            ->whereColumn('ap_seg.entity_id', $entityTable.'.id')
                            ->where('ap_seg.entity_type', $entityMorphClass)
                            ->where('ap_seg.status', 'customer');
                    });
                    break;

                case 'has_link':
                    $this->queryBuilder->whereExists(function ($sub) use ($entityMorphClass, $entityTable) {
                        $sub->select(DB::raw(1))
                            ->from('account_products as ap_seg')
                            ->whereColumn('ap_seg.entity_id', $entityTable.'.id')
                            ->where('ap_seg.entity_type', $entityMorphClass);
                    });
                    break;

                case 'no_link':
                    $this->queryBuilder->whereNotExists(function ($sub) use ($entityMorphClass, $entityTable) {
                        $sub->select(DB::raw(1))
                            ->from('account_products as ap_seg')
                            ->whereColumn('ap_seg.entity_id', $entityTable.'.id')
                            ->where('ap_seg.entity_type', $entityMorphClass);
                    });
                    break;
            }
        }
    }

    /**
     * Get dropdown options for the product filter.
     */
    protected function getProductFilterOptions(): array
    {
        return DB::table('crm_products')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($product) => [
                'label' => $product->name,
                'value' => $product->name,
            ])
            ->toArray();
    }

    /**
     * Get dropdown options for the status filter.
     */
    protected function getStatusFilterOptions(): array
    {
        return collect(AccountProductStatus::cases())
            ->map(fn ($case) => [
                'label' => $case->label(),
                'value' => $case->value,
            ])
            ->toArray();
    }

    /**
     * Get dropdown options for the quick segment filter.
     */
    protected function getSegmentFilterOptions(): array
    {
        return [
            ['label' => 'Cliente de qualquer produto', 'value' => 'customer_any'],
            ['label' => 'Não é cliente',               'value' => 'non_customer'],
            ['label' => 'Possui vínculo comercial',     'value' => 'has_link'],
            ['label' => 'Sem vínculo comercial',        'value' => 'no_link'],
        ];
    }

    /**
     * Render status badges HTML from a GROUP_CONCAT string.
     */
    protected function renderStatusBadges(?string $commercialStatus): string
    {
        if (empty($commercialStatus)) {
            return '<span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">—</span>';
        }

        $statusColors = [
            'lead' => 'bg-blue-100 text-blue-700',
            'prospect' => 'bg-purple-100 text-purple-700',
            'opportunity' => 'bg-yellow-100 text-yellow-700',
            'customer' => 'bg-green-100 text-green-700',
            'inactive_customer' => 'bg-gray-200 text-gray-600',
            'former_customer' => 'bg-orange-100 text-orange-600',
            'lost' => 'bg-red-100 text-red-600',
        ];

        return collect(explode(', ', $commercialStatus))
            ->map(function ($status) use ($statusColors) {
                $status = trim($status);
                $label = AccountProductStatus::tryFrom($status)?->label() ?? $status;
                $color = $statusColors[$status] ?? 'bg-gray-100 text-gray-500';

                return '<span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium '.$color.'">'.e($label).'</span>';
            })
            ->join(' ');
    }
}
