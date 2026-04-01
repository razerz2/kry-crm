<?php

namespace Webkul\Commercial\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Webkul\Commercial\Models\CommercialCampaign;
use Webkul\Commercial\Models\CommercialCampaignAudience;
use Webkul\Commercial\Services\Audience\AudienceFilter;
use Webkul\Commercial\Services\Audience\AudienceItem;
use Webkul\Commercial\Services\Audience\CommercialAudienceService;

class CommercialCampaignService
{
    public function __construct(
        protected CommercialAudienceService $audienceService,
        protected CommercialCampaignStateGuard $stateGuard,
    ) {}

    /**
     * Create a new campaign.
     */
    public function create(array $data): CommercialCampaign
    {
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();
        $data['status'] = $data['status'] ?? 'draft';

        if (isset($data['filters']) && is_array($data['filters'])) {
            $data['filters_json'] = $data['filters'];
            unset($data['filters']);
        }

        return CommercialCampaign::create($data);
    }

    /**
     * Update an existing campaign.
     *
     * Enforces state-machine rules via CommercialCampaignStateGuard:
     *   - read-only statuses (sending/sent/partially_sent/failed/archived) block all edits
     *   - audience-sensitive field changes (channel, filters) while status=ready
     *     cause the frozen audience to be cleared and status reverted to draft
     *
     * @throws \RuntimeException with translatable key if blocked
     */
    public function update(CommercialCampaign $campaign, array $data): CommercialCampaign
    {
        $this->stateGuard->assertEditable($campaign);

        $data['updated_by'] = Auth::id();

        if (isset($data['filters']) && is_array($data['filters'])) {
            $data['filters_json'] = $data['filters'];
            unset($data['filters']);
        }

        // Audience invalidation: if an audience-sensitive field changed while the
        // campaign is ready (or draft with an existing audience), clear the freeze
        // so the operator must re-freeze before dispatching.
        if ($this->stateGuard->audienceNeedsInvalidation($campaign, $data)) {
            $campaign->audienceMembers()->delete();
            $data['audience_generated_at'] = null;
            $data['total_audience'] = 0;
            $data['total_with_email'] = 0;
            $data['total_with_phone'] = 0;

            // If actively operable, revert to draft so execution is blocked until re-freeze
            if (in_array($campaign->status, ['ready', 'scheduled', 'paused'], true)) {
                $data['status'] = 'draft';
                $data['next_run_at'] = null;
            }
        }

        $campaign->update($data);

        return $campaign->fresh();
    }

    /**
     * Mark a draft campaign as "ready" for dispatch.
     *
     * Performs a full readiness check and surfaces all issues as a single
     * translatable exception message.
     *
     * @throws \RuntimeException with pipe-separated issue keys
     */
    public function markReady(CommercialCampaign $campaign): CommercialCampaign
    {
        if (! in_array($campaign->status, ['draft'], true)) {
            throw new \RuntimeException('campaign.mark-ready.not-draft');
        }

        // Surface ALL issues at once so the operator can fix them in one pass
        $issues = $this->stateGuard->readinessIssues($campaign);

        // Filter issues that would still block after status becomes ready
        $blocking = array_filter($issues, fn ($k) => $k !== 'campaign.not-ready.is-draft');

        if (! empty($blocking)) {
            throw new \RuntimeException('campaign.mark-ready.issues:'.implode('|', $blocking));
        }

        $campaign->update(['status' => 'ready', 'updated_by' => Auth::id()]);

        return $campaign->fresh();
    }

    /**
     * Revert a "ready" campaign back to draft.
     */
    public function markDraft(CommercialCampaign $campaign): CommercialCampaign
    {
        $this->stateGuard->assertEditable($campaign);

        $campaign->update(['status' => 'draft', 'updated_by' => Auth::id()]);

        return $campaign->fresh();
    }

    /**
     * Generate a preview of the audience based on filters (not persisted).
     *
     * @return array{items: Collection, stats: array}
     */
    public function generatePreview(array $filters, int $limit = 20): array
    {
        $filter = $this->buildAudienceFilter($filters);
        $filter->limit = $limit;

        return $this->audienceService->forCampaignPreview($filter);
    }

    /**
     * Freeze (persist) the full audience for a campaign.
     *
     * Deletes any existing audience, rebuilds from filters, and saves all members.
     * Updates the campaign totals and audience_generated_at timestamp.
     *
     * @throws \RuntimeException if campaign is locked
     */
    public function freezeAudience(CommercialCampaign $campaign, bool $enforceGuard = true): CommercialCampaign
    {
        if ($enforceGuard) {
            $this->stateGuard->assertAudienceCanBeUpdated($campaign);
        }

        $filters = $campaign->filters_json ?? [];
        $filter = $this->buildAudienceFilter($filters);
        $filter->limit = 0; // no limit for full freeze

        $items = $this->audienceService->build($filter);

        // Delete existing audience in bulk
        $campaign->audienceMembers()->delete();

        // Insert audience members in chunks
        $this->persistAudienceItems($campaign, $items);

        // Update campaign totals
        $stats = $this->audienceService->computeStats($items);

        $campaign->update([
            'audience_generated_at' => now(),
            'total_audience' => $stats['total'],
            'total_with_email' => $stats['with_email'],
            'total_with_phone' => $stats['with_phone'],
            'updated_by' => Auth::id(),
        ]);

        return $campaign->fresh();
    }

    /**
     * Refresh audience during an execution run.
     *
     * This bypasses the edit-state guard because recurring executions run while
     * campaign status is "running".
     */
    public function freezeAudienceForExecution(CommercialCampaign $campaign): CommercialCampaign
    {
        return $this->freezeAudience($campaign, false);
    }

    /**
     * Recalculate: delete old audience, rebuild, and freeze.
     * Alias for freezeAudience (same logic).
     */
    public function recalculateAudience(CommercialCampaign $campaign): CommercialCampaign
    {
        return $this->freezeAudience($campaign);
    }

    /**
     * Get the frozen audience for a campaign (paginated or all).
     *
     * @return Collection<int, CommercialCampaignAudience>
     */
    public function getAudience(CommercialCampaign $campaign, int $limit = 0): Collection
    {
        $query = $campaign->audienceMembers()->orderBy('display_name');

        if ($limit > 0) {
            return $query->limit($limit)->get();
        }

        return $query->get();
    }

    /**
     * Duplicate a campaign for reuse.
     *
     * Copies: name (prefixed), description, channel, subject, message_body,
     *         filters_json.
     * Does NOT copy: audience, deliveries, sent_at, dispatched_at, status
     *                (always starts as draft).
     *
     * Audience is intentionally NOT copied because:
     *   - The audience snapshot may be days/weeks old
     *   - Contacts/statuses may have changed since the original freeze
     *   - The operator must consciously freeze a fresh audience for each send
     */
    public function duplicate(CommercialCampaign $campaign): CommercialCampaign
    {
        return CommercialCampaign::create([
            'name' => trans('admin::app.commercial.campaigns.duplicate.name-prefix').' '.$campaign->name,
            'description' => $campaign->description,
            'channel' => $campaign->channel,
            'subject' => $campaign->subject,
            'message_body' => $campaign->message_body,
            'filters_json' => $campaign->filters_json,
            'status' => 'draft',
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);
    }

    /**
     * Expose the state-guard readiness issues for use in controller / views.
     *
     * @return string[]
     */
    public function readinessIssues(CommercialCampaign $campaign): array
    {
        return $this->stateGuard->readinessIssues($campaign);
    }

    /**
     * Check whether the frozen audience is stale (filters changed after freeze).
     */
    public function isAudienceStale(CommercialCampaign $campaign): bool
    {
        return $this->stateGuard->isAudienceStale($campaign);
    }

    /**
     * Build an AudienceFilter from the campaign's persisted filter JSON.
     */
    public function buildAudienceFilter(array $filters): AudienceFilter
    {
        return AudienceFilter::fromArray([
            'entity_type' => $filters['entity_type'] ?? 'both',
            'crm_product_ids' => $filters['crm_product_ids'] ?? [],
            'commercial_statuses' => $filters['commercial_statuses'] ?? [],
            'segment' => $filters['segment'] ?? null,
            'channel' => $filters['channel'] ?? null,
            'only_with_email' => $filters['only_with_email'] ?? false,
            'only_with_phone' => $filters['only_with_phone'] ?? false,
            'only_primary_contact_if_organization' => $filters['only_primary_contact_if_organization'] ?? false,
            'include_inactive_customer' => $filters['include_inactive_customer'] ?? true,
            'include_former_customer' => $filters['include_former_customer'] ?? true,
            'search' => $filters['search'] ?? null,
        ]);
    }

    /**
     * Persist AudienceItem collection as CommercialCampaignAudience rows.
     *
     * @param  Collection<int, AudienceItem>  $items
     */
    protected function persistAudienceItems(CommercialCampaign $campaign, Collection $items): void
    {
        $rows = $items->map(function (AudienceItem $item) use ($campaign) {
            return [
                'commercial_campaign_id' => $campaign->id,
                'entity_type' => $item->entityType,
                'entity_id' => $item->entityId,
                'display_name' => $item->displayName,
                'organization_name' => $item->organizationName,
                'primary_contact_person_id' => null,
                'primary_contact_name' => null,
                'email' => $item->email,
                'phone' => $item->phone,
                'available_channels' => json_encode($item->availableChannels),
                'crm_products' => json_encode($item->crmProducts),
                'commercial_statuses' => json_encode($item->commercialStatuses),
                'source_summary' => $item->sourceSummary,
                'payload_json' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        });

        // Chunk insert for performance
        foreach ($rows->chunk(500) as $chunk) {
            CommercialCampaignAudience::insert($chunk->toArray());
        }
    }
}
