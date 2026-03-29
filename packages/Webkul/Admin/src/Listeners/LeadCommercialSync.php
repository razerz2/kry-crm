<?php

namespace Webkul\Admin\Listeners;

use Webkul\Commercial\Services\CommercialLeadSyncService;
use Webkul\Lead\Models\Lead;

class LeadCommercialSync
{
    /**
     * Create a new listener instance.
     */
    public function __construct(
        protected CommercialLeadSyncService $syncService
    ) {}

    /**
     * Handle the lead.update.after event.
     *
     * Determines whether the lead stage is won/lost/intermediate
     * and delegates to the appropriate sync method.
     */
    public function afterUpdate($lead): void
    {
        if (! $lead instanceof Lead) {
            return;
        }

        // Skip leads without a CRM product
        if (! $lead->crm_product_id) {
            return;
        }

        // Ensure the stage relationship is loaded
        if (! $lead->relationLoaded('stage')) {
            $lead->load('stage');
        }

        if (! $lead->stage) {
            return;
        }

        $stageCode = $lead->stage->code;

        match ($stageCode) {
            'won'  => $this->syncService->syncWon($lead),
            'lost' => $this->syncService->syncLost($lead),
            default => $this->syncService->syncIntermediateStage($lead),
        };
    }

    /**
     * Handle the lead.create.after event.
     *
     * When a lead is created with a CRM product and is already
     * placed on a non-default stage, sync accordingly.
     */
    public function afterCreate($lead): void
    {
        if (! $lead instanceof Lead) {
            return;
        }

        if (! $lead->crm_product_id) {
            return;
        }

        if (! $lead->relationLoaded('stage')) {
            $lead->load('stage');
        }

        if (! $lead->stage) {
            return;
        }

        $stageCode = $lead->stage->code;

        // On create, sync for won/lost or intermediate stages
        match ($stageCode) {
            'won'  => $this->syncService->syncWon($lead),
            'lost' => $this->syncService->syncLost($lead),
            default => $this->syncService->syncIntermediateStage($lead),
        };
    }
}
