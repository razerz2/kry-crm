<?php

namespace Webkul\Commercial\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Webkul\Commercial\Jobs\ExecuteScheduledCommercialCampaignJob;
use Webkul\Commercial\Models\CommercialCampaign;
use Webkul\Commercial\Models\CommercialCampaignRun;

class CampaignScheduleService
{
    public const EXECUTION_TYPES = ['manual', 'once', 'recurring', 'windowed_recurring'];

    public const RECURRENCE_TYPES = ['daily', 'weekly', 'monthly', 'interval'];

    public const INTERVAL_UNITS = ['minutes', 'hours', 'days'];

    /**
     * Build normalized scheduling data ready for campaign persistence.
     */
    public function prepareCampaignData(array $input, ?CommercialCampaign $campaign = null): array
    {
        $timezone = (string) ($input['timezone'] ?? $campaign?->timezone ?? config('app.timezone', 'UTC'));
        $executionType = (string) ($input['execution_type'] ?? $campaign?->execution_type ?? 'manual');
        $executionType = in_array($executionType, self::EXECUTION_TYPES, true) ? $executionType : 'manual';

        $recurrenceType = (string) ($input['recurrence_type'] ?? $campaign?->recurrence_type ?? '');
        $recurrenceType = in_array($recurrenceType, self::RECURRENCE_TYPES, true) ? $recurrenceType : null;

        $intervalUnit = (string) ($input['interval_unit'] ?? $campaign?->interval_unit ?? '');
        $intervalUnit = in_array($intervalUnit, self::INTERVAL_UNITS, true) ? $intervalUnit : null;

        $daysOfWeek = $this->normaliseDaysOfWeek($input['days_of_week'] ?? $campaign?->days_of_week ?? []);

        $data = [
            'execution_type' => $executionType,
            'timezone' => $timezone,
            'run_at' => $this->parseDateTimeToUtc($input['run_at'] ?? null, $timezone),
            'starts_at' => $this->parseDateTimeToUtc($input['starts_at'] ?? null, $timezone),
            'ends_at' => $this->parseDateTimeToUtc($input['ends_at'] ?? null, $timezone),
            'recurrence_type' => $recurrenceType,
            'interval_value' => $this->nullableInt($input['interval_value'] ?? null),
            'interval_unit' => $intervalUnit,
            'days_of_week' => $daysOfWeek,
            'day_of_month' => $this->nullableInt($input['day_of_month'] ?? null),
            'time_of_day' => $this->normaliseTime($input['time_of_day'] ?? $campaign?->time_of_day),
            'window_start_time' => $this->normaliseTime($input['window_start_time'] ?? $campaign?->window_start_time),
            'window_end_time' => $this->normaliseTime($input['window_end_time'] ?? $campaign?->window_end_time),
            'max_runs' => $this->nullableInt($input['max_runs'] ?? null),
        ];

        if ($executionType === 'manual') {
            $data = array_merge($data, [
                'run_at' => null,
                'starts_at' => null,
                'ends_at' => null,
                'recurrence_type' => null,
                'interval_value' => null,
                'interval_unit' => null,
                'days_of_week' => null,
                'day_of_month' => null,
                'time_of_day' => null,
                'window_start_time' => null,
                'window_end_time' => null,
                'max_runs' => null,
                'next_run_at' => null,
            ]);

            return $data;
        }

        if ($executionType === 'once') {
            $data = array_merge($data, [
                'recurrence_type' => null,
                'interval_value' => null,
                'interval_unit' => null,
                'days_of_week' => null,
                'day_of_month' => null,
                'time_of_day' => null,
                'window_start_time' => null,
                'window_end_time' => null,
            ]);
        } else {
            $data['run_at'] = null;
        }

        if (! in_array($executionType, ['windowed_recurring'], true)) {
            $data['window_start_time'] = null;
            $data['window_end_time'] = null;
        }

        if (! in_array($recurrenceType, ['interval'], true)) {
            $data['interval_value'] = null;
            $data['interval_unit'] = null;
        }

        if (! in_array($recurrenceType, ['weekly'], true)) {
            $data['days_of_week'] = null;
        }

        if (! in_array($recurrenceType, ['monthly'], true)) {
            $data['day_of_month'] = null;
        }

        if (! in_array($recurrenceType, ['daily', 'weekly', 'monthly'], true)) {
            $data['time_of_day'] = null;
        }

        $next = $this->calculateNextRunAtFromData($data, null, $campaign?->last_run_at);
        $data['next_run_at'] = $next;

        return $data;
    }

    /**
     * Returns due campaign IDs for automatic scheduler scan.
     */
    public function dueCampaignIds(int $limit = 25): Collection
    {
        return CommercialCampaign::query()
            ->where('status', 'scheduled')
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->orderBy('next_run_at')
            ->limit(max(1, $limit))
            ->pluck('id');
    }

    /**
     * Queue an immediate execution request.
     */
    public function queueImmediateExecution(CommercialCampaign $campaign, ?int $userId = null): void
    {
        ExecuteScheduledCommercialCampaignJob::dispatch($campaign->id, 'manual', true, $userId)
            ->onQueue(config('commercial.campaign.queue', 'default'));
    }

    /**
     * Put campaign in scheduled state with recalculated next_run_at.
     */
    public function scheduleCampaign(CommercialCampaign $campaign, ?int $userId = null): CommercialCampaign
    {
        if ($campaign->execution_type === 'manual') {
            throw new RuntimeException('Campaign is configured as manual. Choose an automatic execution type first.');
        }

        if (in_array($campaign->status, ['running', 'canceled'], true)) {
            throw new RuntimeException("Campaign cannot be scheduled while status is {$campaign->status}.");
        }

        if (! $campaign->hasAudience()) {
            throw new RuntimeException('Campaign has no frozen audience. Freeze the audience before scheduling.');
        }

        if (blank($campaign->message_body)) {
            throw new RuntimeException('Campaign message body is required before scheduling.');
        }

        if (in_array($campaign->channel, ['email', 'both'], true) && blank($campaign->subject)) {
            throw new RuntimeException('Campaign subject is required for email channels before scheduling.');
        }

        $nextRunAt = $this->calculateNextRunAt($campaign);

        if (! $nextRunAt) {
            throw new RuntimeException('Unable to calculate next execution time with current scheduling settings.');
        }

        $campaign->update([
            'status' => 'scheduled',
            'next_run_at' => $nextRunAt,
            'updated_by' => $userId ?? Auth::id(),
        ]);

        return $campaign->fresh();
    }

    public function pauseCampaign(CommercialCampaign $campaign, ?int $userId = null): CommercialCampaign
    {
        if (! in_array($campaign->status, ['scheduled', 'running'], true)) {
            throw new RuntimeException("Only scheduled or running campaigns can be paused (current: {$campaign->status}).");
        }

        $campaign->update([
            'status' => 'paused',
            'updated_by' => $userId ?? Auth::id(),
        ]);

        return $campaign->fresh();
    }

    public function resumeCampaign(CommercialCampaign $campaign, ?int $userId = null): CommercialCampaign
    {
        if ($campaign->status !== 'paused') {
            throw new RuntimeException("Only paused campaigns can be resumed (current: {$campaign->status}).");
        }

        if ($campaign->execution_type === 'manual') {
            $campaign->update([
                'status' => 'ready',
                'updated_by' => $userId ?? Auth::id(),
            ]);

            return $campaign->fresh();
        }

        $nextRunAt = $this->calculateNextRunAt($campaign);

        if (! $nextRunAt) {
            throw new RuntimeException('Unable to resume campaign because no next execution can be calculated.');
        }

        $campaign->update([
            'status' => 'scheduled',
            'next_run_at' => $nextRunAt,
            'updated_by' => $userId ?? Auth::id(),
        ]);

        return $campaign->fresh();
    }

    public function cancelCampaign(CommercialCampaign $campaign, ?int $userId = null): CommercialCampaign
    {
        DB::transaction(function () use ($campaign, $userId) {
            $campaign->update([
                'status' => 'canceled',
                'next_run_at' => null,
                'updated_by' => $userId ?? Auth::id(),
            ]);

            DB::table('commercial_campaign_deliveries')
                ->where('commercial_campaign_id', $campaign->id)
                ->whereIn('status', ['pending', 'queued'])
                ->update([
                    'status' => 'canceled',
                    'failure_reason' => 'Canceled by operator',
                    'updated_at' => now(),
                ]);
        });

        return $campaign->fresh();
    }

    /**
     * Claim campaign execution and create a run record atomically.
     */
    public function startExecutionRun(
        int $campaignId,
        string $triggerType = 'scheduler',
        bool $force = false,
        ?int $userId = null
    ): ?CommercialCampaignRun {
        return DB::transaction(function () use ($campaignId, $triggerType, $force, $userId) {
            $campaign = CommercialCampaign::query()->lockForUpdate()->find($campaignId);

            if (! $campaign) {
                return null;
            }

            if ($campaign->status === 'canceled') {
                return null;
            }

            if ($campaign->status === 'running') {
                return null;
            }

            if ($this->hasReachedMaxRuns($campaign)) {
                $campaign->update([
                    'status' => 'completed',
                    'next_run_at' => null,
                ]);

                return null;
            }

            if (! $force && $triggerType === 'scheduler') {
                if ($campaign->status !== 'scheduled') {
                    return null;
                }

                if (! $campaign->next_run_at || $campaign->next_run_at->isFuture()) {
                    return null;
                }
            }

            if ($triggerType === 'manual' && ! in_array($campaign->status, ['draft', 'ready', 'scheduled', 'paused'], true)) {
                return null;
            }

            $scheduledFor = $triggerType === 'scheduler'
                ? $campaign->next_run_at
                : now();

            $run = CommercialCampaignRun::create([
                'commercial_campaign_id' => $campaign->id,
                'trigger_type' => $triggerType,
                'status' => 'running',
                'scheduled_for' => $scheduledFor,
                'started_at' => now(),
                'created_by' => $userId ?? Auth::id(),
                'meta_json' => ['forced' => $force],
            ]);

            $campaign->update([
                'status' => 'running',
                'dispatched_at' => now(),
                'last_run_at' => now(),
                'next_run_at' => null,
                'updated_by' => $userId ?? Auth::id(),
            ]);

            return $run;
        });
    }

    /**
     * Attach audience snapshot counters from campaign freeze to a run.
     */
    public function syncRunAudienceSnapshot(CommercialCampaignRun $run, CommercialCampaign $campaign): void
    {
        $run->update([
            'audience_total' => (int) $campaign->total_audience,
            'audience_with_email' => (int) $campaign->total_with_email,
            'audience_with_phone' => (int) $campaign->total_with_phone,
        ]);
    }

    /**
     * Finalize run + campaign state after all run deliveries are terminal.
     */
    public function finalizeRun(int $campaignId, int $runId): void
    {
        $lock = Cache::lock("commercial-campaign-run-finalize:{$runId}", 30);

        if (! $lock->get()) {
            return;
        }

        try {
            DB::transaction(function () use ($campaignId, $runId) {
                $campaign = CommercialCampaign::query()->lockForUpdate()->find($campaignId);
                $run = CommercialCampaignRun::query()->lockForUpdate()->find($runId);

                if (! $campaign || ! $run || $run->commercial_campaign_id !== $campaign->id) {
                    return;
                }

                $inProgress = DB::table('commercial_campaign_deliveries')
                    ->where('commercial_campaign_run_id', $run->id)
                    ->whereIn('status', ['pending', 'queued', 'sending'])
                    ->count();

                if ($inProgress > 0) {
                    return;
                }

                $snapshot = $this->runDeliverySnapshot($run->id);

                $runStatus = 'completed';
                if ($snapshot['total'] === 0 || ($snapshot['sent'] === 0 && $snapshot['failed'] > 0)) {
                    $runStatus = 'failed';
                }
                if ($snapshot['total'] > 0 && $snapshot['total'] === $snapshot['canceled']) {
                    $runStatus = 'canceled';
                }

                $run->update([
                    'status' => $runStatus,
                    'finished_at' => now(),
                    'total_deliveries' => $snapshot['total'],
                    'total_sent' => $snapshot['sent'],
                    'total_failed' => $snapshot['failed'],
                    'total_skipped' => $snapshot['skipped'],
                    'total_canceled' => $snapshot['canceled'],
                ]);

                $this->applyPostRunCampaignState($campaign);
            });
        } finally {
            $lock->release();
        }
    }

    public function failRun(int $campaignId, int $runId, string $reason): void
    {
        DB::transaction(function () use ($campaignId, $runId, $reason) {
            $campaign = CommercialCampaign::query()->lockForUpdate()->find($campaignId);
            $run = CommercialCampaignRun::query()->lockForUpdate()->find($runId);

            if (! $campaign || ! $run || $run->commercial_campaign_id !== $campaign->id) {
                return;
            }

            $run->update([
                'status' => 'failed',
                'error_message' => mb_substr($reason, 0, 2000),
                'finished_at' => now(),
            ]);

            $this->applyPostRunCampaignState($campaign);
        });
    }

    public function recalculateNextRun(CommercialCampaign $campaign, ?int $userId = null): CommercialCampaign
    {
        $next = $this->calculateNextRunAt($campaign);

        $campaign->update([
            'next_run_at' => $next,
            'updated_by' => $userId ?? Auth::id(),
        ]);

        return $campaign->fresh();
    }

    /**
     * Calculate next run timestamp for persisted campaign.
     */
    public function calculateNextRunAt(CommercialCampaign $campaign, ?CarbonInterface $reference = null): ?Carbon
    {
        return $this->calculateNextRunAtFromData($campaign->toArray(), $reference, $campaign->last_run_at);
    }

    /**
     * Used by scheduler command to enqueue due runs.
     */
    public function enqueueDueCampaigns(int $limit = 25): int
    {
        $queue = config('commercial.campaign.queue', 'default');
        $count = 0;

        foreach ($this->dueCampaignIds($limit) as $campaignId) {
            ExecuteScheduledCommercialCampaignJob::dispatch((int) $campaignId, 'scheduler', false, null)
                ->onQueue($queue);

            $count++;
        }

        return $count;
    }

    protected function applyPostRunCampaignState(CommercialCampaign $campaign): void
    {
        if ($campaign->status === 'canceled') {
            return;
        }

        $nextRunAt = null;
        $nextStatus = 'completed';

        if (in_array($campaign->execution_type, ['recurring', 'windowed_recurring'], true) && ! $this->hasReachedMaxRuns($campaign)) {
            $nextRunAt = $this->calculateNextRunAt($campaign, now());
            $nextStatus = $nextRunAt ? 'scheduled' : 'completed';
        }

        if ($campaign->execution_type === 'once') {
            $nextStatus = 'completed';
            $nextRunAt = null;
        }

        if ($campaign->execution_type === 'manual') {
            $nextStatus = 'ready';
            $nextRunAt = null;
        }

        $campaign->update([
            'status' => $nextStatus,
            'next_run_at' => $nextRunAt,
            'sent_at' => now(),
        ]);
    }

    protected function hasReachedMaxRuns(CommercialCampaign $campaign): bool
    {
        if (! $campaign->max_runs || $campaign->max_runs <= 0) {
            return false;
        }

        $finishedRuns = $campaign->runs()
            ->whereIn('status', ['completed', 'failed', 'canceled'])
            ->count();

        return $finishedRuns >= $campaign->max_runs;
    }

    protected function runDeliverySnapshot(int $runId): array
    {
        $rows = DB::table('commercial_campaign_deliveries')
            ->where('commercial_campaign_run_id', $runId)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $statuses = ['pending', 'queued', 'sending', 'sent', 'failed', 'skipped', 'canceled'];

        $snapshot = [];
        foreach ($statuses as $status) {
            $snapshot[$status] = (int) ($rows[$status] ?? 0);
        }

        $snapshot['total'] = array_sum($snapshot);

        return $snapshot;
    }

    protected function calculateNextRunAtFromData(
        array $data,
        ?CarbonInterface $reference = null,
        mixed $lastRunAt = null
    ): ?Carbon {
        $executionType = (string) ($data['execution_type'] ?? 'manual');
        $timezone = (string) ($data['timezone'] ?? config('app.timezone', 'UTC'));

        if ($executionType === 'manual') {
            return null;
        }

        $base = $reference
            ? Carbon::parse($reference)->setTimezone($timezone)
            : now($timezone);

        $startsAt = $this->toTimezone($data['starts_at'] ?? null, $timezone);
        $endsAt = $this->toTimezone($data['ends_at'] ?? null, $timezone);

        if ($startsAt && $base->lt($startsAt)) {
            $base = $startsAt->copy();
        }

        if ($endsAt && $base->gt($endsAt)) {
            return null;
        }

        if ($executionType === 'once') {
            $runAt = $this->toTimezone($data['run_at'] ?? null, $timezone);

            if (! $runAt) {
                return null;
            }

            $lastRun = $this->toTimezone($lastRunAt, $timezone);

            if ($lastRun) {
                return null;
            }

            if ($endsAt && $runAt->gt($endsAt)) {
                return null;
            }

            return $runAt->setTimezone('UTC');
        }

        $recurrenceType = (string) ($data['recurrence_type'] ?? '');
        if (! in_array($recurrenceType, self::RECURRENCE_TYPES, true)) {
            return null;
        }

        $candidate = match ($recurrenceType) {
            'daily' => $this->nextDaily($base, $data['time_of_day'] ?? null),
            'weekly' => $this->nextWeekly($base, $data['time_of_day'] ?? null, $data['days_of_week'] ?? []),
            'monthly' => $this->nextMonthly($base, $data['time_of_day'] ?? null, $this->nullableInt($data['day_of_month'] ?? null)),
            'interval' => $this->nextInterval(
                $base,
                $startsAt ?? $base,
                $this->toTimezone($lastRunAt, $timezone),
                $this->nullableInt($data['interval_value'] ?? null),
                (string) ($data['interval_unit'] ?? '')
            ),
            default => null,
        };

        if (! $candidate) {
            return null;
        }

        if ($executionType === 'windowed_recurring') {
            $candidate = $this->applyWindow($candidate, $data['window_start_time'] ?? null, $data['window_end_time'] ?? null);
        }

        if ($startsAt && $candidate->lt($startsAt)) {
            $candidate = $startsAt->copy();
        }

        if ($endsAt && $candidate->gt($endsAt)) {
            return null;
        }

        return $candidate->setTimezone('UTC');
    }

    protected function parseDateTimeToUtc(mixed $value, string $timezone): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value, $timezone)->setTimezone('UTC');
    }

    protected function toTimezone(mixed $value, string $timezone): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->setTimezone($timezone);
    }

    protected function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(1, (int) $value);
    }

    protected function normaliseTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = (string) $value;

        if (preg_match('/^\d{2}:\d{2}$/', $value)) {
            return "{$value}:00";
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value)) {
            return $value;
        }

        return null;
    }

    protected function normaliseDaysOfWeek(mixed $days): array
    {
        if (! is_array($days)) {
            return [];
        }

        $days = array_map(static fn ($day) => (int) $day, $days);
        $days = array_filter($days, static fn ($day) => $day >= 0 && $day <= 6);
        $days = array_values(array_unique($days));
        sort($days);

        return $days;
    }

    protected function nextDaily(Carbon $base, mixed $timeOfDay): ?Carbon
    {
        $time = $this->normaliseTime($timeOfDay) ?? '09:00:00';
        $candidate = $base->copy()->setTimeFromTimeString($time);

        if ($candidate->lte($base)) {
            $candidate->addDay();
        }

        return $candidate;
    }

    protected function nextWeekly(Carbon $base, mixed $timeOfDay, mixed $days): ?Carbon
    {
        $time = $this->normaliseTime($timeOfDay) ?? '09:00:00';
        $weekDays = $this->normaliseDaysOfWeek($days);

        if (empty($weekDays)) {
            $weekDays = [$base->dayOfWeek];
        }

        for ($offset = 0; $offset <= 7; $offset++) {
            $day = $base->copy()->addDays($offset);
            if (! in_array($day->dayOfWeek, $weekDays, true)) {
                continue;
            }

            $candidate = $day->copy()->setTimeFromTimeString($time);
            if ($candidate->gt($base)) {
                return $candidate;
            }
        }

        return null;
    }

    protected function nextMonthly(Carbon $base, mixed $timeOfDay, ?int $dayOfMonth): ?Carbon
    {
        $time = $this->normaliseTime($timeOfDay) ?? '09:00:00';
        $dayOfMonth = $dayOfMonth ?: $base->day;

        for ($offset = 0; $offset <= 24; $offset++) {
            $monthRef = $base->copy()->startOfMonth()->addMonthsNoOverflow($offset);
            $day = min($dayOfMonth, $monthRef->daysInMonth);
            $candidate = $monthRef->copy()->setDay($day)->setTimeFromTimeString($time);

            if ($candidate->gt($base)) {
                return $candidate;
            }
        }

        return null;
    }

    protected function nextInterval(
        Carbon $base,
        Carbon $anchor,
        ?Carbon $lastRunAt,
        ?int $intervalValue,
        string $intervalUnit
    ): ?Carbon {
        $value = $intervalValue ?: 1;
        $unit = in_array($intervalUnit, self::INTERVAL_UNITS, true) ? $intervalUnit : 'hours';

        $candidate = ($lastRunAt ?: $anchor)->copy();

        if ($candidate->lte($base)) {
            $safety = 0;
            while ($candidate->lte($base) && $safety < 5000) {
                $candidate = $this->addInterval($candidate, $value, $unit);
                $safety++;
            }
        }

        return $candidate;
    }

    protected function addInterval(Carbon $date, int $value, string $unit): Carbon
    {
        return match ($unit) {
            'minutes' => $date->copy()->addMinutes($value),
            'days' => $date->copy()->addDays($value),
            default => $date->copy()->addHours($value),
        };
    }

    protected function applyWindow(Carbon $candidate, mixed $windowStart, mixed $windowEnd): Carbon
    {
        $start = $this->normaliseTime($windowStart);
        $end = $this->normaliseTime($windowEnd);

        if (! $start || ! $end) {
            return $candidate;
        }

        $windowStartAt = $candidate->copy()->setTimeFromTimeString($start);
        $windowEndAt = $candidate->copy()->setTimeFromTimeString($end);

        if ($windowStartAt->gt($windowEndAt)) {
            return $candidate;
        }

        if ($candidate->lt($windowStartAt)) {
            return $windowStartAt;
        }

        if ($candidate->gt($windowEndAt)) {
            return $windowStartAt->addDay();
        }

        return $candidate;
    }
}
