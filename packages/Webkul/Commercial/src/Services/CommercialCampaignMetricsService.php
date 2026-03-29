<?php

namespace Webkul\Commercial\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Webkul\Commercial\Models\CommercialCampaign;

class CommercialCampaignMetricsService
{
    /**
     * All delivery statuses tracked in aggregated counters.
     */
    public const STATUSES = ['pending', 'queued', 'sending', 'sent', 'failed', 'skipped', 'canceled'];

    /* ── Core refresh ────────────────────────────────────────────── */

    /**
     * Recompute all per-status delivery counts, persist them on the campaign
     * row, and return the full snapshot.
     *
     * @return array{pending:int,queued:int,sending:int,sent:int,failed:int,skipped:int,canceled:int,total:int,in_progress:int}
     */
    public function refresh(CommercialCampaign $campaign): array
    {
        $rows = DB::table('commercial_campaign_deliveries')
            ->where('commercial_campaign_id', $campaign->id)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $snapshot = [];
        foreach (self::STATUSES as $s) {
            $snapshot[$s] = (int) ($rows[$s] ?? 0);
        }
        $snapshot['total']       = array_sum(array_intersect_key($snapshot, array_flip(self::STATUSES)));
        $snapshot['in_progress'] = $snapshot['pending'] + $snapshot['queued'] + $snapshot['sending'];

        $campaign->update([
            'total_deliveries' => $snapshot['total'],
            'total_pending'    => $snapshot['pending'],
            'total_queued'     => $snapshot['queued'],
            'total_sending'    => $snapshot['sending'],
            'total_sent'       => $snapshot['sent'],
            'total_failed'     => $snapshot['failed'],
            'total_skipped'    => $snapshot['skipped'],
            'total_canceled'   => $snapshot['canceled'],
        ]);

        return $snapshot;
    }

    /* ── Status-final rule ───────────────────────────────────────── */

    /**
     * Derive campaign final status from an aggregated snapshot.
     *
     * Rules (skipped/canceled are neutral — they never block "sent"):
     *   sent > 0 && failed == 0  →  sent
     *   sent > 0 && failed > 0   →  partially_sent
     *   sent == 0 && failed > 0  →  failed
     *   sent == 0 && failed == 0 →  sent  (all were skipped/canceled)
     */
    public function computeFinalStatus(array $snapshot): string
    {
        $sent   = $snapshot['sent'];
        $failed = $snapshot['failed'];

        if ($sent > 0 && $failed === 0) {
            return 'sent';
        }

        if ($sent > 0 && $failed > 0) {
            return 'partially_sent';
        }

        if ($failed > 0) {
            return 'failed';
        }

        return 'sent'; // all skipped/canceled — no actionable failures
    }

    /* ── Breakdowns (not persisted) ─────────────────────────────── */

    /**
     * Returns per-channel status counts, pivoted into a nested array:
     *   [ 'email' => ['total' => N, 'sent' => X, 'failed' => Y, ...], ... ]
     */
    public function channelBreakdown(int $campaignId): array
    {
        $rows = DB::table('commercial_campaign_deliveries')
            ->where('commercial_campaign_id', $campaignId)
            ->selectRaw('channel, status, COUNT(*) as cnt')
            ->groupBy('channel', 'status')
            ->get();

        return $this->pivot($rows, 'channel');
    }

    /**
     * Returns per-provider status counts, pivoted into a nested array:
     *   [ 'internal_email' => ['total' => N, 'sent' => X, ...], ... ]
     */
    public function providerBreakdown(int $campaignId): array
    {
        $rows = DB::table('commercial_campaign_deliveries')
            ->where('commercial_campaign_id', $campaignId)
            ->selectRaw('provider, status, COUNT(*) as cnt')
            ->groupBy('provider', 'status')
            ->get();

        return $this->pivot($rows, 'provider');
    }

    /**
     * Returns the most recent failed deliveries for an error summary.
     */
    public function recentErrors(int $campaignId, int $limit = 5): Collection
    {
        return DB::table('commercial_campaign_deliveries')
            ->where('commercial_campaign_id', $campaignId)
            ->where('status', 'failed')
            ->whereNotNull('failure_reason')
            ->orderByDesc('failed_at')
            ->limit($limit)
            ->select([
                'id',
                'recipient_name',
                'recipient_email',
                'recipient_phone',
                'channel',
                'provider',
                'failure_reason',
                'failed_at',
            ])
            ->get();
    }

    /* ── Private ─────────────────────────────────────────────────── */

    /**
     * Pivot a flat status-count collection into a nested breakdown array.
     */
    private function pivot(Collection $rows, string $groupKey): array
    {
        $blank = array_fill_keys(self::STATUSES, 0) + ['total' => 0];

        $result = [];
        foreach ($rows as $row) {
            $key = $row->{$groupKey} ?? 'unknown';

            if (! isset($result[$key])) {
                $result[$key] = $blank;
            }

            $status = $row->status;

            if (in_array($status, self::STATUSES, true)) {
                $result[$key][$status] = (int) $row->cnt;
            }

            $result[$key]['total'] += (int) $row->cnt;
        }

        return $result;
    }
}
