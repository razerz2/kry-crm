<?php

namespace Webkul\Commercial\Services;

use Illuminate\Support\Facades\Log;
use Webkul\Commercial\Enums\AccountProductStatus;
use Webkul\Commercial\Models\AccountProduct;
use Webkul\Commercial\Models\AccountProductHistory;
use Webkul\Contact\Models\OrganizationProxy;
use Webkul\Contact\Models\PersonProxy;
use Webkul\Lead\Models\Lead;

class CommercialLeadSyncService
{
    /**
     * Stage code => AccountProductStatus mapping for intermediate stages.
     *
     * Pipeline stages (from PipelineSeeder):
     *   new        => lead
     *   follow-up  => lead
     *   prospect   => prospect
     *   negotiation => opportunity
     *   won        => customer   (handled by syncWon)
     *   lost       => lost       (handled by syncLost)
     */
    protected const STAGE_STATUS_MAP = [
        'new' => AccountProductStatus::LEAD,
        'follow-up' => AccountProductStatus::LEAD,
        'prospect' => AccountProductStatus::PROSPECT,
        'negotiation' => AccountProductStatus::OPPORTUNITY,
    ];

    /**
     * Status hierarchy — higher index = higher priority.
     * A status can only be overwritten by an equal or higher priority status.
     * This prevents downgrading a customer to lead/prospect/lost.
     */
    protected const STATUS_HIERARCHY = [
        'lead' => 10,
        'prospect' => 20,
        'opportunity' => 30,
        'customer' => 100,
        'inactive_customer' => 90,
        'former_customer' => 80,
        'lost' => 5,
    ];

    /**
     * Synchronize commercial relationship when a lead is marked as WON.
     *
     * - Creates or updates AccountProduct with status = customer
     * - Sets started_at if not already set
     * - Records history entry
     */
    public function syncWon(Lead $lead): void
    {
        if (! $lead->crm_product_id) {
            return;
        }

        $entity = $this->resolveCommercialEntity($lead);

        if (! $entity) {
            Log::warning('[CommercialSync] Lead #'.$lead->id.' won but no entity resolved.');

            return;
        }

        $this->upsertAccountProduct(
            entityType: $entity['type'],
            entityId: $entity['id'],
            crmProductId: $lead->crm_product_id,
            targetStatus: AccountProductStatus::CUSTOMER,
            lead: $lead,
            extra: [
                'user_id' => $lead->user_id,
            ]
        );
    }

    /**
     * Synchronize commercial relationship when a lead is marked as LOST.
     *
     * - Only downgrades if current status allows it (hierarchy check)
     * - Never overwrites customer/inactive_customer/former_customer
     * - Records lost_reason from lead if available
     * - Sets ended_at
     */
    public function syncLost(Lead $lead): void
    {
        if (! $lead->crm_product_id) {
            return;
        }

        $entity = $this->resolveCommercialEntity($lead);

        if (! $entity) {
            return;
        }

        $this->upsertAccountProduct(
            entityType: $entity['type'],
            entityId: $entity['id'],
            crmProductId: $lead->crm_product_id,
            targetStatus: AccountProductStatus::LOST,
            lead: $lead,
            extra: array_filter([
                'lost_reason' => $lead->lost_reason,
                'user_id' => $lead->user_id,
            ])
        );
    }

    /**
     * Synchronize commercial relationship for intermediate pipeline stages.
     *
     * Mapping:
     *   new / follow-up  => lead
     *   prospect          => prospect
     *   negotiation       => opportunity
     *
     * Only creates or upgrades — never downgrades existing status.
     */
    public function syncIntermediateStage(Lead $lead): void
    {
        if (! $lead->crm_product_id) {
            return;
        }

        $stageCode = $lead->stage?->code;

        if (! $stageCode || ! isset(self::STAGE_STATUS_MAP[$stageCode])) {
            return;
        }

        $entity = $this->resolveCommercialEntity($lead);

        if (! $entity) {
            return;
        }

        $targetStatus = self::STAGE_STATUS_MAP[$stageCode];

        $this->upsertAccountProduct(
            entityType: $entity['type'],
            entityId: $entity['id'],
            crmProductId: $lead->crm_product_id,
            targetStatus: $targetStatus,
            lead: $lead,
            extra: [
                'user_id' => $lead->user_id,
            ]
        );
    }

    /**
     * Resolve the commercial entity (Person or Organization) from a Lead.
     *
     * Priority:
     * 1. If the lead has a person with an organization => Organization
     * 2. If the lead has a person without organization => Person
     * 3. Otherwise => null
     *
     * @return array{type: string, id: int}|null
     */
    public function resolveCommercialEntity(Lead $lead): ?array
    {
        if (! $lead->person_id) {
            return null;
        }

        $person = $lead->person;

        if (! $person) {
            return null;
        }

        // If the person belongs to an organization, use the organization as the commercial entity
        if ($person->organization_id) {
            return [
                'type' => OrganizationProxy::modelClass(),
                'id' => $person->organization_id,
            ];
        }

        // Otherwise, use the person directly
        return [
            'type' => PersonProxy::modelClass(),
            'id' => $person->id,
        ];
    }

    /**
     * Create or update an AccountProduct record, respecting status hierarchy.
     *
     * Uses entity_type + entity_id + crm_product_id as the unique key.
     * Never creates duplicates — uses updateOrCreate logic.
     * Never downgrades a higher-priority status.
     */
    public function upsertAccountProduct(
        string $entityType,
        int $entityId,
        int $crmProductId,
        AccountProductStatus $targetStatus,
        Lead $lead,
        array $extra = []
    ): ?AccountProduct {
        $existing = AccountProduct::where([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'crm_product_id' => $crmProductId,
        ])->first();

        if ($existing) {
            $currentStatusValue = $existing->status instanceof AccountProductStatus
                ? $existing->status->value
                : (string) $existing->status;

            // Check hierarchy — do not downgrade
            if (! $this->canTransition($currentStatusValue, $targetStatus->value)) {
                Log::info('[CommercialSync] Skipping downgrade: AccountProduct #'.$existing->id
                    .' current='.$currentStatusValue
                    .' target='.$targetStatus->value
                    .' lead=#'.$lead->id);

                return $existing;
            }

            // Same status — no change needed
            if ($currentStatusValue === $targetStatus->value) {
                return $existing;
            }

            $oldStatus = $currentStatusValue;

            // Build update data
            $updateData = array_merge($extra, [
                'status' => $targetStatus->value,
            ]);

            if ($targetStatus === AccountProductStatus::CUSTOMER) {
                // Set started_at only if not already set (never overwrite)
                if (! $existing->started_at) {
                    $updateData['started_at'] = now();
                }

                // Clear ended_at on reactivation (e.g. was lost, now won again)
                $updateData['ended_at'] = null;
            }

            // Set ended_at for lost/former_customer
            if (in_array($targetStatus, [AccountProductStatus::LOST, AccountProductStatus::FORMER_CUSTOMER])) {
                $updateData['ended_at'] = now();
            }

            $existing->update($updateData);

            $this->recordHistory(
                accountProductId: $existing->id,
                oldStatus: $oldStatus,
                newStatus: $targetStatus->value,
                lead: $lead,
                source: 'lead_sync',
                notes: 'Auto-sync from lead #'.$lead->id.' stage: '.($lead->stage?->code ?? 'unknown')
            );

            return $existing->fresh();
        }

        // Create new AccountProduct
        $createData = array_merge($extra, [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'crm_product_id' => $crmProductId,
            'status' => $targetStatus->value,
        ]);

        if ($targetStatus === AccountProductStatus::CUSTOMER) {
            $createData['started_at'] = now();
        }

        if (in_array($targetStatus, [AccountProductStatus::LOST, AccountProductStatus::FORMER_CUSTOMER])) {
            $createData['ended_at'] = now();
        }

        $accountProduct = AccountProduct::create($createData);

        $this->recordHistory(
            accountProductId: $accountProduct->id,
            oldStatus: null,
            newStatus: $targetStatus->value,
            lead: $lead,
            source: 'lead_sync',
            notes: 'Created from lead #'.$lead->id.' stage: '.($lead->stage?->code ?? 'unknown')
        );

        return $accountProduct;
    }

    /**
     * Record a status change in account_product_histories.
     */
    public function recordHistory(
        int $accountProductId,
        ?string $oldStatus,
        string $newStatus,
        Lead $lead,
        string $source = 'lead_sync',
        ?string $notes = null
    ): void {
        AccountProductHistory::create([
            'account_product_id' => $accountProductId,
            'lead_id' => $lead->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => $lead->user_id,
            'source' => $source,
            'notes' => $notes,
        ]);
    }

    /**
     * Check if a transition from $currentStatus to $targetStatus is allowed.
     *
     * Returns true if the target status has equal or higher hierarchy priority.
     * Returns false if it would be a downgrade.
     */
    protected function canTransition(string $currentStatus, string $targetStatus): bool
    {
        $currentPriority = self::STATUS_HIERARCHY[$currentStatus] ?? 0;
        $targetPriority = self::STATUS_HIERARCHY[$targetStatus] ?? 0;

        return $targetPriority >= $currentPriority;
    }
}
