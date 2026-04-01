<?php

namespace Webkul\Commercial\Services;

use Webkul\Commercial\Models\CommercialCampaign;

/**
 * Single source of truth for campaign state-machine rules.
 *
 * Answers three questions:
 *   1. canEdit()         — may the user change any field?
 *   2. requiresAudienceInvalidation() — does changing these fields require
 *                          the frozen audience to be cleared?
 *   3. canArchive() / canDelete()
 *
 * All guards throw \RuntimeException with a translatable key so callers
 * can surface them in the UI without inspecting the exception class.
 */
class CommercialCampaignStateGuard
{
    /**
     * Fields that, if changed while status=ready, invalidate the frozen
     * audience and revert the campaign to draft.
     */
    public const AUDIENCE_SENSITIVE_FIELDS = [
        'channel',
        'filters_json',
    ];

    /**
     * Fields that are completely blocked when status is in LOCKED_STATUSES.
     * (name and description are always editable for audit purposes when
     *  the campaign is sent/failed — they do not affect the send.)
     */
    public const LOCKED_STATUSES = CommercialCampaign::LOCKED_STATUSES;

    /* ── Read-only statuses ──────────────────────────────────────── */

    /**
     * Statuses where ALL editing is forbidden.
     * archived is read-only by policy; the locked statuses (sending/sent/…)
     * are blocked for data-integrity reasons.
     */
    public const READ_ONLY_STATUSES = ['running', 'sending', 'sent', 'partially_sent', 'failed', 'archived'];

    /* ── Guards ──────────────────────────────────────────────────── */

    /**
     * Assert the campaign may be edited at all.
     *
     * @throws \RuntimeException
     */
    public function assertEditable(CommercialCampaign $campaign): void
    {
        if (in_array($campaign->status, self::READ_ONLY_STATUSES, true)) {
            throw new \RuntimeException(
                "campaign.edit-blocked:{$campaign->status}"
            );
        }
    }

    /**
     * Assert the campaign may have its audience frozen/recalculated.
     *
     * @throws \RuntimeException
     */
    public function assertAudienceCanBeUpdated(CommercialCampaign $campaign): void
    {
        if (in_array($campaign->status, self::LOCKED_STATUSES, true)) {
            throw new \RuntimeException(
                "campaign.audience-blocked:{$campaign->status}"
            );
        }
    }

    /**
     * Assert the campaign may be dispatched.
     *
     * @throws \RuntimeException
     */
    public function assertDispatchable(CommercialCampaign $campaign): void
    {
        if (! in_array($campaign->status, ['ready', 'scheduled', 'paused'], true)) {
            throw new \RuntimeException(
                "campaign.dispatch-not-ready:{$campaign->status}"
            );
        }

        if (! $campaign->hasAudience()) {
            throw new \RuntimeException('campaign.dispatch-no-audience');
        }
    }

    /**
     * Assert the campaign may be deleted.
     * Sent / partially_sent campaigns require explicit archive first.
     *
     * @throws \RuntimeException
     */
    public function assertDeletable(CommercialCampaign $campaign): void
    {
        if (in_array($campaign->status, ['sending'], true)) {
            throw new \RuntimeException('campaign.delete-sending');
        }
    }

    /* ── Audience invalidation logic ─────────────────────────────── */

    /**
     * Given the current campaign and the incoming update data, decide
     * whether the frozen audience must be cleared.
     *
     * Returns true when:
     *   - status is draft or ready (audience exists or could exist)
     *   - AND at least one audience-sensitive field changed
     */
    public function audienceNeedsInvalidation(CommercialCampaign $campaign, array $incomingData): bool
    {
        if (! $campaign->hasAudience()) {
            return false;
        }

        // channel change
        if (isset($incomingData['channel']) && $incomingData['channel'] !== $campaign->channel) {
            return true;
        }

        // filters_json change — deep compare to avoid noise
        if (isset($incomingData['filters_json'])) {
            $currentFilters = $campaign->filters_json ?? [];
            $incomingFilters = $incomingData['filters_json'];

            if ($this->filtersChanged($currentFilters, $incomingFilters)) {
                return true;
            }
        }

        return false;
    }

    /**
     * True if the campaign has a frozen audience but content fields
     * (subject, message_body) differ from what is stored — meaning
     * the rendered messages in deliveries (if they exist) may not
     * match what would be sent today.
     *
     * Used only for the UI "stale content" warning — not a hard block.
     */
    public function hasStaleContent(CommercialCampaign $campaign, array $incomingData): bool
    {
        if (! $campaign->hasAudience()) {
            return false;
        }

        foreach (['subject', 'message_body'] as $field) {
            if (isset($incomingData[$field]) && $incomingData[$field] !== $campaign->{$field}) {
                return true;
            }
        }

        return false;
    }

    /**
     * Audience is considered "stale" (outdated compared to current filters)
     * when audience_generated_at is older than the last update to the campaign's
     * filters. We detect this by comparing updated_at vs audience_generated_at.
     *
     * Returns true when the frozen audience may no longer reflect current filters.
     * Used for UI warning only.
     */
    public function isAudienceStale(CommercialCampaign $campaign): bool
    {
        if (! $campaign->hasAudience()) {
            return false;
        }

        // If the campaign was edited after the audience was frozen, it may be stale.
        return $campaign->updated_at > $campaign->audience_generated_at;
    }

    /* ── Readiness check ─────────────────────────────────────────── */

    /**
     * Returns an array of issues that prevent the campaign from being dispatched.
     * Empty array = fully ready.
     *
     * @return string[] list of translatable issue keys
     */
    public function readinessIssues(CommercialCampaign $campaign): array
    {
        $issues = [];

        if (! $campaign->hasAudience()) {
            $issues[] = 'campaign.not-ready.no-audience';
        }

        if (empty($campaign->message_body)) {
            $issues[] = 'campaign.not-ready.no-body';
        }

        if (in_array($campaign->channel, ['email', 'both'], true) && empty($campaign->subject)) {
            $issues[] = 'campaign.not-ready.no-subject';
        }

        if ($this->isAudienceStale($campaign)) {
            $issues[] = 'campaign.not-ready.audience-stale';
        }

        if ($campaign->status === 'draft') {
            $issues[] = 'campaign.not-ready.is-draft';
        }

        return $issues;
    }

    /* ── Private helpers ─────────────────────────────────────────── */

    private function filtersChanged(array $current, array $incoming): bool
    {
        // Normalise both sides before comparing (sort arrays, cast booleans)
        return $this->normalise($current) !== $this->normalise($incoming);
    }

    private function normalise(array $filters): string
    {
        array_walk_recursive($filters, static function (&$v) {
            if (is_bool($v)) {
                $v = $v ? '1' : '0';
            }
            if (is_array($v)) {
                sort($v);
            }
        });
        ksort($filters);

        return serialize($filters);
    }
}
