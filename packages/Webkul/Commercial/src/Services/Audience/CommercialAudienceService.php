<?php

namespace Webkul\Commercial\Services\Audience;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Commercial\Enums\AccountProductStatus;
use Webkul\Contact\Models\OrganizationProxy;
use Webkul\Contact\Models\PersonProxy;

class CommercialAudienceService
{
    /**
     * Build the full audience based on filters.
     *
     * Returns a Collection of AudienceItem objects, deduplicated.
     *
     * @return Collection<int, AudienceItem>
     */
    public function build(AudienceFilter $filter): Collection
    {
        $items = collect();

        if ($filter->includesPersons()) {
            $items = $items->merge($this->queryPersons($filter));
        }

        if ($filter->includesOrganizations()) {
            $items = $items->merge($this->queryOrganizations($filter));
        }

        // Apply channel filters
        if ($filter->requiresEmail()) {
            $items = $items->filter(fn (AudienceItem $item) => $item->hasEmail());
        }

        if ($filter->requiresPhone()) {
            $items = $items->filter(fn (AudienceItem $item) => $item->hasPhone());
        }

        // Apply search filter (name, email, phone)
        if ($filter->search) {
            $search = mb_strtolower($filter->search);
            $items = $items->filter(function (AudienceItem $item) use ($search) {
                return str_contains(mb_strtolower($item->displayName), $search)
                    || str_contains(mb_strtolower($item->email ?? ''), $search)
                    || str_contains(mb_strtolower($item->phone ?? ''), $search)
                    || str_contains(mb_strtolower($item->organizationName ?? ''), $search);
            });
        }

        // Apply limit
        if ($filter->limit > 0) {
            $items = $items->take($filter->limit);
        }

        return $items->values();
    }

    /**
     * Shortcut: audience filtered to only those with email.
     *
     * @return Collection<int, AudienceItem>
     */
    public function forEmail(AudienceFilter $filter): Collection
    {
        $filter->onlyWithEmail = true;

        return $this->build($filter);
    }

    /**
     * Shortcut: audience filtered to only those with phone/WhatsApp.
     *
     * @return Collection<int, AudienceItem>
     */
    public function forWhatsApp(AudienceFilter $filter): Collection
    {
        $filter->onlyWithPhone = true;

        return $this->build($filter);
    }

    /**
     * Shortcut: audience for campaign preview with summary stats.
     *
     * @return array{items: Collection, stats: array}
     */
    public function forCampaignPreview(AudienceFilter $filter): array
    {
        $items = $this->build($filter);

        return [
            'items' => $items,
            'stats' => $this->computeStats($items),
        ];
    }

    /**
     * Compute summary statistics from an audience collection.
     */
    public function computeStats(Collection $items): array
    {
        $total = $items->count();
        $withEmail = $items->filter(fn (AudienceItem $i) => $i->hasEmail())->count();
        $withPhone = $items->filter(fn (AudienceItem $i) => $i->hasPhone())->count();
        $persons = $items->filter(fn (AudienceItem $i) => $i->entityLabel === 'Person')->count();
        $organizations = $items->filter(fn (AudienceItem $i) => $i->entityLabel === 'Organization')->count();

        // Group by status
        $byStatus = [];
        foreach ($items as $item) {
            foreach ($item->commercialStatuses as $status) {
                $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
            }
        }

        // Group by product
        $byProduct = [];
        foreach ($items as $item) {
            foreach ($item->crmProducts as $product) {
                $byProduct[$product] = ($byProduct[$product] ?? 0) + 1;
            }
        }

        return [
            'total' => $total,
            'with_email' => $withEmail,
            'with_phone' => $withPhone,
            'persons' => $persons,
            'organizations' => $organizations,
            'by_status' => $byStatus,
            'by_product' => $byProduct,
        ];
    }

    /**
     * Query persons and map to AudienceItem collection.
     *
     * Heuristic for primary email: first element of the JSON `emails` array.
     * Heuristic for primary phone: first element of the JSON `contact_numbers` array.
     *
     * @return Collection<int, AudienceItem>
     */
    protected function queryPersons(AudienceFilter $filter): Collection
    {
        $personClass = PersonProxy::modelClass();
        $orgClass = OrganizationProxy::modelClass();

        $query = DB::table('persons')
            ->leftJoin('organizations', 'organizations.id', '=', 'persons.organization_id')
            ->select([
                'persons.id',
                'persons.name',
                'persons.emails',
                'persons.contact_numbers',
                'persons.organization_id',
                'organizations.name as organization_name',
            ]);

        $this->applyCommercialFilters($query, $personClass, $filter);
        $this->applySegmentFilter($query, $personClass, $filter);

        // Free-text search at DB level for efficiency
        if ($filter->search) {
            $search = '%'.$filter->search.'%';
            $query->where(function ($q) use ($search) {
                $q->where('persons.name', 'like', $search)
                    ->orWhere('persons.emails', 'like', $search)
                    ->orWhere('persons.contact_numbers', 'like', $search)
                    ->orWhere('organizations.name', 'like', $search);
            });
        }

        $query->groupBy(
            'persons.id',
            'persons.name',
            'persons.emails',
            'persons.contact_numbers',
            'persons.organization_id',
            'organizations.name'
        );

        $rows = $query->get();

        // Load commercial data in batch for all person IDs
        $personIds = $rows->pluck('id')->toArray();
        $commercialMap = $this->loadCommercialData($personClass, $personIds);

        return $rows->map(function ($row) use ($personClass, $commercialMap) {
            $email = $this->extractPrimaryEmail($row->emails);
            $phone = $this->extractPrimaryPhone($row->contact_numbers);

            $commercial = $commercialMap[$row->id] ?? ['products' => [], 'statuses' => [], 'summary' => ''];

            $channels = [];
            if ($email) {
                $channels[] = 'email';
            }
            if ($phone) {
                $channels[] = 'whatsapp';
            }

            return new AudienceItem(
                entityType: $personClass,
                entityId: $row->id,
                entityLabel: 'Person',
                displayName: $row->name ?? '(sem nome)',
                organizationName: $row->organization_name,
                email: $email,
                phone: $phone,
                crmProducts: $commercial['products'],
                commercialStatuses: $commercial['statuses'],
                availableChannels: $channels,
                sourceSummary: $commercial['summary'],
            );
        });
    }

    /**
     * Query organizations and map to AudienceItem collection.
     *
     * Contact resolution rule:
     * 1) Prefer the organization's own emails/contact_numbers
     * 2) Fallback to a linked person when organization contacts are empty
     *
     * @return Collection<int, AudienceItem>
     */
    protected function queryOrganizations(AudienceFilter $filter): Collection
    {
        $orgClass = OrganizationProxy::modelClass();
        $hasOrgEmails = Schema::hasColumn('organizations', 'emails');
        $hasOrgPhones = Schema::hasColumn('organizations', 'contact_numbers');

        $selectColumns = [
            'organizations.id',
            'organizations.name',
        ];

        if ($hasOrgEmails) {
            $selectColumns[] = 'organizations.emails';
        }

        if ($hasOrgPhones) {
            $selectColumns[] = 'organizations.contact_numbers';
        }

        $query = DB::table('organizations')->select($selectColumns);

        $this->applyCommercialFilters($query, $orgClass, $filter);
        $this->applySegmentFilter($query, $orgClass, $filter);

        if ($filter->search) {
            $search = '%'.$filter->search.'%';

            $query->where(function ($q) use ($search, $hasOrgEmails, $hasOrgPhones) {
                $q->where('organizations.name', 'like', $search);

                if ($hasOrgEmails) {
                    $q->orWhere('organizations.emails', 'like', $search);
                }

                if ($hasOrgPhones) {
                    $q->orWhere('organizations.contact_numbers', 'like', $search);
                }
            });
        }

        $groupBy = ['organizations.id', 'organizations.name'];

        if ($hasOrgEmails) {
            $groupBy[] = 'organizations.emails';
        }

        if ($hasOrgPhones) {
            $groupBy[] = 'organizations.contact_numbers';
        }

        $query->groupBy(...$groupBy);

        $rows = $query->get();

        $orgIds = $rows->pluck('id')->toArray();
        $commercialMap = $this->loadCommercialData($orgClass, $orgIds);

        // Load primary contact person per organization for email/phone
        $contactMap = $this->loadPrimaryContacts($orgIds, $filter->onlyPrimaryContactIfOrganization);

        return $rows->map(function ($row) use ($orgClass, $commercialMap, $contactMap) {
            $email = $this->extractPrimaryEmail($row->emails ?? null);
            $phone = $this->extractPrimaryPhone($row->contact_numbers ?? null);

            $contact = $contactMap[$row->id] ?? null;

            if (! $email && $contact) {
                $email = $this->extractPrimaryEmail($contact->emails);
            }

            if (! $phone && $contact) {
                $phone = $this->extractPrimaryPhone($contact->contact_numbers);
            }

            $commercial = $commercialMap[$row->id] ?? ['products' => [], 'statuses' => [], 'summary' => ''];

            $channels = [];
            if ($email) {
                $channels[] = 'email';
            }
            if ($phone) {
                $channels[] = 'whatsapp';
            }

            return new AudienceItem(
                entityType: $orgClass,
                entityId: $row->id,
                entityLabel: 'Organization',
                displayName: $row->name ?? '(sem nome)',
                organizationName: null,
                email: $email,
                phone: $phone,
                crmProducts: $commercial['products'],
                commercialStatuses: $commercial['statuses'],
                availableChannels: $channels,
                sourceSummary: $commercial['summary'],
            );
        });
    }

    /**
     * Apply commercial filters (product IDs, statuses) via EXISTS subqueries.
     *
     * This avoids duplicating rows from JOIN and respects GROUP BY.
     */
    protected function applyCommercialFilters($query, string $entityClass, AudienceFilter $filter): void
    {
        $entityTable = $this->entityTable($entityClass);

        // Filter by specific product IDs
        if (! empty($filter->crmProductIds)) {
            $query->whereExists(function ($sub) use ($entityClass, $entityTable, $filter) {
                $sub->select(DB::raw(1))
                    ->from('account_products')
                    ->whereColumn('account_products.entity_id', $entityTable.'.id')
                    ->where('account_products.entity_type', $entityClass)
                    ->whereIn('account_products.crm_product_id', $filter->crmProductIds);
            });
        }

        // Filter by specific commercial statuses
        if (! empty($filter->commercialStatuses)) {
            $statuses = $this->resolveStatuses($filter);

            $query->whereExists(function ($sub) use ($entityClass, $entityTable, $statuses) {
                $sub->select(DB::raw(1))
                    ->from('account_products')
                    ->whereColumn('account_products.entity_id', $entityTable.'.id')
                    ->where('account_products.entity_type', $entityClass)
                    ->whereIn('account_products.status', $statuses);
            });
        }
    }

    /**
     * Apply segment-level filters (customer_any, non_customer, has_link, no_link).
     */
    protected function applySegmentFilter($query, string $entityClass, AudienceFilter $filter): void
    {
        if (! $filter->segment) {
            return;
        }

        $entityTable = $this->entityTable($entityClass);

        switch ($filter->segment) {
            case AudienceFilter::SEGMENT_CUSTOMER_ANY:
                $query->whereExists(function ($sub) use ($entityClass, $entityTable) {
                    $sub->select(DB::raw(1))
                        ->from('account_products')
                        ->whereColumn('account_products.entity_id', $entityTable.'.id')
                        ->where('account_products.entity_type', $entityClass)
                        ->where('account_products.status', AccountProductStatus::CUSTOMER->value);
                });
                break;

            case AudienceFilter::SEGMENT_NON_CUSTOMER:
                $query->whereNotExists(function ($sub) use ($entityClass, $entityTable) {
                    $sub->select(DB::raw(1))
                        ->from('account_products')
                        ->whereColumn('account_products.entity_id', $entityTable.'.id')
                        ->where('account_products.entity_type', $entityClass)
                        ->where('account_products.status', AccountProductStatus::CUSTOMER->value);
                });
                break;

            case AudienceFilter::SEGMENT_HAS_LINK:
                $query->whereExists(function ($sub) use ($entityClass, $entityTable) {
                    $sub->select(DB::raw(1))
                        ->from('account_products')
                        ->whereColumn('account_products.entity_id', $entityTable.'.id')
                        ->where('account_products.entity_type', $entityClass);
                });
                break;

            case AudienceFilter::SEGMENT_NO_LINK:
                $query->whereNotExists(function ($sub) use ($entityClass, $entityTable) {
                    $sub->select(DB::raw(1))
                        ->from('account_products')
                        ->whereColumn('account_products.entity_id', $entityTable.'.id')
                        ->where('account_products.entity_type', $entityClass);
                });
                break;
        }
    }

    /**
     * Load commercial data (products + statuses) in batch for a set of entity IDs.
     *
     * Returns an associative array keyed by entity_id:
     *   [entity_id => ['products' => [...], 'statuses' => [...], 'summary' => '...']]
     */
    protected function loadCommercialData(string $entityClass, array $entityIds): array
    {
        if (empty($entityIds)) {
            return [];
        }

        $rows = DB::table('account_products')
            ->join('crm_products', 'crm_products.id', '=', 'account_products.crm_product_id')
            ->where('account_products.entity_type', $entityClass)
            ->whereIn('account_products.entity_id', $entityIds)
            ->select([
                'account_products.entity_id',
                'account_products.status',
                'crm_products.name as product_name',
            ])
            ->get();

        $map = [];

        foreach ($rows as $row) {
            $eid = $row->entity_id;

            if (! isset($map[$eid])) {
                $map[$eid] = ['products' => [], 'statuses' => [], 'summary_parts' => []];
            }

            if (! in_array($row->product_name, $map[$eid]['products'])) {
                $map[$eid]['products'][] = $row->product_name;
            }

            if (! in_array($row->status, $map[$eid]['statuses'])) {
                $map[$eid]['statuses'][] = $row->status;
            }

            $statusLabel = $this->statusLabel($row->status);
            $map[$eid]['summary_parts'][] = $row->product_name.': '.$statusLabel;
        }

        // Build summary strings
        foreach ($map as $eid => &$data) {
            $data['summary'] = implode(', ', array_unique($data['summary_parts']));
            unset($data['summary_parts']);
        }

        return $map;
    }

    /**
     * Load primary contact person for each organization.
     *
     * Heuristic: first person (by ID) with a valid email, falling back to any person.
     *
     * @return array<int, object> Keyed by organization_id
     */
    protected function loadPrimaryContacts(array $orgIds, bool $onlyPrimary = false): array
    {
        if (empty($orgIds)) {
            return [];
        }

        $persons = DB::table('persons')
            ->whereIn('organization_id', $orgIds)
            ->select(['id', 'organization_id', 'name', 'emails', 'contact_numbers'])
            ->orderBy('id')
            ->get();

        $map = [];

        foreach ($persons as $person) {
            $orgId = $person->organization_id;

            // If we already have a contact with email for this org, skip
            if (isset($map[$orgId])) {
                $existingEmail = $this->extractPrimaryEmail($map[$orgId]->emails);

                if ($existingEmail) {
                    continue;
                }
            }

            // Prefer a person that has an email
            $email = $this->extractPrimaryEmail($person->emails);

            if ($email || ! isset($map[$orgId])) {
                $map[$orgId] = $person;
            }

            // If onlyPrimary, stop after the first person with email
            if ($onlyPrimary && $email) {
                continue;
            }
        }

        return $map;
    }

    /**
     * Extract the primary email from the JSON `emails` field.
     *
     * JSON format: [{"value": "email@example.com", "label": "work"}, ...]
     * Rule: prefer primary contact entry, otherwise first valid email.
     */
    public function extractPrimaryEmail(?string $json): ?string
    {
        $entries = $this->decodeContactEntries($json);
        $preferred = $this->pickPreferredContactEntry($entries, true);

        if (! $preferred) {
            return null;
        }

        $value = trim((string) ($preferred['value'] ?? ''));

        return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
    }

    /**
     * Extract the primary phone from the JSON `contact_numbers` field.
     *
     * JSON format: [{"value": "+5511999999999", "label": "work"}, ...]
     * Rule: prefer primary contact entry, otherwise first valid phone.
     */
    public function extractPrimaryPhone(?string $json): ?string
    {
        $entries = $this->decodeContactEntries($json);
        $preferred = $this->pickPreferredContactEntry($entries, false);

        if (! $preferred) {
            return null;
        }

        $value = trim((string) ($preferred['value'] ?? ''));

        return $value !== '' ? $value : null;
    }

    /**
     * Decode emails/contact_numbers JSON into a list of associative entries.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function decodeContactEntries(?string $json): array
    {
        if (! $json) {
            return [];
        }

        $entries = json_decode($json, true);

        return is_array($entries) ? $entries : [];
    }

    /**
     * Choose a deterministic contact entry.
     *
     * Priority:
     * 1) entry explicitly marked as primary/principal
     * 2) first valid entry in list order
     *
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<string, mixed>|null
     */
    protected function pickPreferredContactEntry(array $entries, bool $email): ?array
    {
        $validEntries = array_values(array_filter($entries, function ($entry) use ($email) {
            if (! is_array($entry)) {
                return false;
            }

            $value = trim((string) ($entry['value'] ?? ''));

            if ($value === '') {
                return false;
            }

            if ($email) {
                return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
            }

            return preg_replace('/\D+/', '', $value) !== '';
        }));

        if (empty($validEntries)) {
            return null;
        }

        foreach ($validEntries as $entry) {
            if ($this->isPrimaryContactEntry($entry)) {
                return $entry;
            }
        }

        return $validEntries[0];
    }

    /**
     * Determine if a contact entry is marked as primary.
     *
     * @param  array<string, mixed>  $entry
     */
    protected function isPrimaryContactEntry(array $entry): bool
    {
        foreach (['is_primary', 'primary', 'isPrimary', 'principal', 'default', 'main'] as $flagKey) {
            if (! array_key_exists($flagKey, $entry)) {
                continue;
            }

            $value = $entry[$flagKey];

            if ($value === true || $value === 1 || $value === '1') {
                return true;
            }

            if (is_string($value) && in_array(mb_strtolower(trim($value)), ['true', 'yes', 'sim'], true)) {
                return true;
            }
        }

        $label = mb_strtolower(trim((string) ($entry['label'] ?? '')));

        return in_array($label, ['primary', 'principal', 'main', 'default'], true);
    }

    /**
     * Resolve commercial statuses from filter, accounting for include flags.
     *
     * @return string[]
     */
    protected function resolveStatuses(AudienceFilter $filter): array
    {
        $statuses = $filter->commercialStatuses;

        // Remove inactive_customer/former_customer if not included
        if (! $filter->includeInactiveCustomer) {
            $statuses = array_filter($statuses, fn ($s) => $s !== AccountProductStatus::INACTIVE_CUSTOMER->value);
        }

        if (! $filter->includeFormerCustomer) {
            $statuses = array_filter($statuses, fn ($s) => $s !== AccountProductStatus::FORMER_CUSTOMER->value);
        }

        return array_values($statuses);
    }

    /**
     * Get a human-readable label for a status value.
     */
    protected function statusLabel(string $status): string
    {
        $enum = AccountProductStatus::tryFrom($status);

        return $enum ? $enum->label() : $status;
    }

    /**
     * Get the base table name for an entity class.
     */
    protected function entityTable(string $entityClass): string
    {
        if ($entityClass === PersonProxy::modelClass()) {
            return 'persons';
        }

        return 'organizations';
    }
}
