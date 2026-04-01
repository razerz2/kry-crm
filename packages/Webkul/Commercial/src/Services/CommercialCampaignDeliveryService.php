<?php

namespace Webkul\Commercial\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Commercial\Jobs\DispatchCommercialCampaignJob;
use Webkul\Commercial\Models\CommercialCampaign;
use Webkul\Commercial\Models\CommercialCampaignAudience;
use Webkul\Commercial\Models\CommercialCampaignDelivery;
use Webkul\Commercial\Models\CommercialCampaignDeliveryLog;
use Webkul\Commercial\Repositories\CommercialCampaignDeliveryRepository;
use Webkul\Commercial\Services\Sending\CampaignChannelSenderInterface;
use Webkul\Commercial\Services\Sending\EmailCampaignSender;
use Webkul\Commercial\Services\Sending\WhatsAppCampaignSender;
use Webkul\Commercial\Services\Template\CommercialCampaignTemplateRenderer;
use Webkul\Commercial\Services\Template\TemplateRenderContext;

class CommercialCampaignDeliveryService
{
    /**
     * Statuses that lock a campaign from further edits.
     */
    public const LOCKED_STATUSES = ['running', 'sending', 'sent', 'partially_sent', 'failed'];

    public function __construct(
        protected CommercialCampaignDeliveryRepository $deliveryRepo,
        protected EmailCampaignSender $emailSender,
        protected WhatsAppCampaignSender $whatsappSender,
        protected CommercialCampaignTemplateRenderer $renderer,
        protected CommercialCampaignMetricsService $metricsService,
    ) {}

    /* ── Dispatch lifecycle ──────────────────────────────────────── */

    /**
     * Validate preconditions and start the dispatch.
     *
     * Dispatches the background job for one campaign execution run.
     * Does NOT create delivery rows synchronously.
     *
     * @throws \RuntimeException if campaign is not dispatchable
     */
    public function dispatch(
        CommercialCampaign $campaign,
        ?int $campaignRunId = null,
        bool $updateCampaignState = true
    ): void {
        if (! in_array($campaign->status, ['ready', 'scheduled', 'running', 'paused'], true)) {
            throw new \RuntimeException(
                "Campaign #{$campaign->id} is not dispatchable in the current status ({$campaign->status})."
            );
        }

        if (! $campaign->hasAudience()) {
            throw new \RuntimeException(
                "Campaign #{$campaign->id} has no frozen audience. Freeze the audience before dispatching."
            );
        }

        if ($updateCampaignState) {
            $campaign->update([
                'status' => 'running',
                'dispatched_at' => now(),
                'updated_by' => Auth::id(),
            ]);
        }

        DispatchCommercialCampaignJob::dispatch($campaign->id, $campaignRunId)
            ->onQueue(config('commercial.campaign.queue', 'default'));
    }

    /* ── Delivery creation ───────────────────────────────────────── */

    /**
     * Materialise delivery rows from the frozen audience.
     *
     * Called inside DispatchCommercialCampaignJob.
     * Idempotent: skips rows that already exist (unique constraint on
     * campaign_id + audience_id + channel).
     *
     * @return int number of rows inserted
     */
    public function createDeliveries(CommercialCampaign $campaign, ?int $campaignRunId = null): int
    {
        $campaignChannel = $campaign->channel;
        $insertedTotal = 0;
        $chunkSize = (int) config('commercial.campaign.delivery_chunk_size', 200);
        $dedupe = $this->loadDeliveryDedupeState($campaign->id, $campaignRunId);

        $campaign->audienceMembers()
            ->chunkById($chunkSize, function ($members) use ($campaign, $campaignChannel, $campaignRunId, &$insertedTotal, &$dedupe) {
                $rows = [];

                foreach ($members as $member) {
                    foreach ($this->buildDeliveryRows($campaign, $member, $campaignChannel, $campaignRunId, $dedupe) as $row) {
                        $rows[] = $row;
                    }
                }

                if (empty($rows)) {
                    return;
                }

                // Bulk insert with duplicate ignore
                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('commercial_campaign_deliveries')->insertOrIgnore($chunk);
                    $insertedTotal += count($chunk);
                }
            });

        // Update total_deliveries on campaign
        $total = CommercialCampaignDelivery::where('commercial_campaign_id', $campaign->id)->count();
        $campaign->update(['total_deliveries' => $total]);

        return $total;
    }

    /**
     * Build delivery row(s) for a single audience member.
     * Returns an array of row arrays (may be 0, 1 or 2 rows depending on channel).
     */
    protected function buildDeliveryRows(
        CommercialCampaign $campaign,
        CommercialCampaignAudience $member,
        string $campaignChannel,
        ?int $campaignRunId = null,
        array &$dedupe = []
    ): array {
        $rows = [];
        $now = now()->toDateTimeString();
        $whatsAppProvider = $this->resolveWhatsAppProvider();
        $base = [
            'commercial_campaign_id' => $campaign->id,
            'commercial_campaign_run_id' => $campaignRunId,
            'commercial_campaign_audience_id' => $member->id,
            'entity_type' => $member->entity_type,
            'entity_id' => $member->entity_id,
            'recipient_name' => $member->display_name,
            'created_by' => $campaign->updated_by ?? $campaign->created_by,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (in_array($campaignChannel, ['email', 'both'], true)) {
            $this->appendDeliveryRow(
                rows: $rows,
                dedupe: $dedupe,
                campaign: $campaign,
                member: $member,
                base: $base,
                channel: 'email',
                provider: config('commercial.campaign.email_provider', 'internal_email'),
                destination: $member->email,
            );
        }

        if (in_array($campaignChannel, ['whatsapp', 'both'], true)) {
            $this->appendDeliveryRow(
                rows: $rows,
                dedupe: $dedupe,
                campaign: $campaign,
                member: $member,
                base: $base,
                channel: 'whatsapp',
                provider: $whatsAppProvider,
                destination: $member->phone,
            );
        }

        return $rows;
    }

    protected function appendDeliveryRow(
        array &$rows,
        array &$dedupe,
        CommercialCampaign $campaign,
        CommercialCampaignAudience $member,
        array $base,
        string $channel,
        ?string $provider,
        ?string $destination
    ): void {
        $entityKey = $this->entityDedupeKey(
            (int) ($base['commercial_campaign_run_id'] ?? 0),
            (string) $member->entity_type,
            (int) $member->entity_id,
            $channel
        );

        if (isset($dedupe['entity'][$entityKey])) {
            return;
        }

        $normalizedDestination = $this->normalizeDestination($channel, $destination);

        if ($normalizedDestination) {
            $destinationKey = $this->destinationDedupeKey(
                (int) ($base['commercial_campaign_run_id'] ?? 0),
                $channel,
                $normalizedDestination
            );

            if (isset($dedupe['destination'][$destinationKey])) {
                return;
            }
        }

        $ctx = TemplateRenderContext::fromAudienceMember($member, $campaign, $channel);
        $row = array_merge($base, [
            'channel' => $channel,
            'provider' => $provider,
            'recipient_email' => null,
            'recipient_phone' => null,
            'subject' => null,
            'failure_reason' => null,
            'rendered_message' => '',
        ]);

        if ($this->hasNormalizedDestinationColumn()) {
            $row['normalized_destination'] = $normalizedDestination;
        }

        if ($channel === 'email') {
            if ($destination && $normalizedDestination) {
                $row['recipient_email'] = $destination;
                $row['subject'] = $this->renderer->renderSubject($campaign->subject ?? '', $ctx);
                $row['rendered_message'] = $this->renderer->renderBody($campaign->message_body ?? '', $ctx);
                $row['status'] = 'pending';
            } else {
                $row['status'] = 'skipped';
                $row['failure_reason'] = 'No email address';
            }
        } else {
            if ($destination && $normalizedDestination) {
                $row['recipient_phone'] = $destination;
                $row['rendered_message'] = $this->renderer->renderBody($campaign->message_body ?? '', $ctx);
                $row['status'] = 'pending';
            } else {
                $row['status'] = 'skipped';
                $row['failure_reason'] = 'No phone number';
            }
        }

        $rows[] = $row;
        $dedupe['entity'][$entityKey] = true;

        if (! empty($destinationKey ?? null)) {
            $dedupe['destination'][$destinationKey] = true;
        }
    }

    protected function loadDeliveryDedupeState(int $campaignId, ?int $campaignRunId): array
    {
        $state = [
            'entity' => [],
            'destination' => [],
        ];

        $query = DB::table('commercial_campaign_deliveries')
            ->where('commercial_campaign_id', $campaignId)
            ->select([
                'id',
                'commercial_campaign_run_id',
                'entity_type',
                'entity_id',
                'channel',
                'recipient_email',
                'recipient_phone',
            ]);

        $hasNormalizedDestination = $this->hasNormalizedDestinationColumn();

        if ($hasNormalizedDestination) {
            $query->addSelect('normalized_destination');
        }

        if ($campaignRunId !== null) {
            $query->where('commercial_campaign_run_id', $campaignRunId);
        } else {
            $query->whereNull('commercial_campaign_run_id');
        }

        $query->orderBy('id')
            ->chunkById(500, function ($rows) use (&$state, $hasNormalizedDestination) {
                foreach ($rows as $row) {
                    $runId = (int) ($row->commercial_campaign_run_id ?? 0);
                    $channel = (string) $row->channel;

                    $state['entity'][$this->entityDedupeKey(
                        $runId,
                        (string) $row->entity_type,
                        (int) $row->entity_id,
                        $channel
                    )] = true;

                    $normalized = ($hasNormalizedDestination ? $row->normalized_destination : null)
                        ?: $this->normalizeDestination(
                            $channel,
                            $channel === 'email' ? $row->recipient_email : $row->recipient_phone
                        );

                    if ($normalized) {
                        $state['destination'][$this->destinationDedupeKey($runId, $channel, $normalized)] = true;
                    }
                }
            }, 'id');

        return $state;
    }

    protected function entityDedupeKey(int $runId, string $entityType, int $entityId, string $channel): string
    {
        return $runId.'|'.$entityType.'|'.$entityId.'|'.$channel;
    }

    protected function destinationDedupeKey(int $runId, string $channel, string $normalizedDestination): string
    {
        return $runId.'|'.$channel.'|'.$normalizedDestination;
    }

    protected function normalizeDestination(string $channel, ?string $destination): ?string
    {
        $destination = trim((string) $destination);

        if ($destination === '') {
            return null;
        }

        if ($channel === 'email') {
            $normalized = mb_strtolower($destination);

            return filter_var($normalized, FILTER_VALIDATE_EMAIL) ? $normalized : null;
        }

        $digits = preg_replace('/\D+/', '', $destination) ?: '';

        return $digits !== '' ? $digits : null;
    }

    protected function hasNormalizedDestinationColumn(): bool
    {
        static $hasColumn = null;

        if ($hasColumn === null) {
            $hasColumn = Schema::hasColumn('commercial_campaign_deliveries', 'normalized_destination');
        }

        return $hasColumn;
    }

    /* ── Individual delivery processing ─────────────────────────── */

    /**
     * Process (send) a single delivery record.
     *
     * Called from SendCommercialCampaignDeliveryJob.
     * Handles status transitions and error logging.
     */
    public function processDelivery(CommercialCampaignDelivery $delivery): void
    {
        if (! in_array($delivery->status, ['pending', 'queued'], true)) {
            return; // Already processed or terminal state
        }

        $delivery->update(['status' => 'sending']);

        try {
            $sender = $this->getSender($delivery->channel);
            $sender->send($delivery);

            $delivery->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            $this->log($delivery, 'info', 'Delivery sent successfully.');

        } catch (\Throwable $e) {
            $delivery->update([
                'status' => 'failed',
                'failure_reason' => mb_substr($e->getMessage(), 0, 500),
                'failed_at' => now(),
            ]);

            $this->log($delivery, 'error', 'Delivery failed: '.$e->getMessage(), [
                'exception' => get_class($e),
            ]);
        }
    }

    /* ── Campaign final-status resolution ───────────────────────── */

    /**
     * Recompute and persist the campaign's final status and counters after
     * all deliveries have been processed.
     *
     * Called at the end of each SendCommercialCampaignDeliveryJob when
     * no deliveries remain in progress.
     */
    /**
     * Recompute all delivery counters via MetricsService and, if all deliveries
     * have reached a terminal state, set the campaign's final status.
     *
     * Delegates the actual SQL aggregation and status-rule logic to
     * CommercialCampaignMetricsService to keep a single source of truth.
     */
    public function updateCampaignFinalStatus(CommercialCampaign $campaign): void
    {
        $snapshot = $this->metricsService->refresh($campaign);

        if ($snapshot['in_progress'] > 0) {
            return;
        }

        $finalStatus = $this->metricsService->computeFinalStatus($snapshot);

        $campaign->update([
            'status' => $finalStatus,
            'sent_at' => now(),
        ]);
    }

    /* ── Stats & helpers ─────────────────────────────────────────── */

    /**
     * Return per-status counts for a campaign's deliveries.
     *
     * @return array{pending:int,queued:int,sending:int,sent:int,failed:int,skipped:int,canceled:int,total:int}
     */
    public function getDeliveryStats(CommercialCampaign $campaign): array
    {
        return $this->deliveryRepo->statsByCampaign($campaign->id);
    }

    /**
     * True if the campaign is in a state where edits, re-freezes and
     * audience recalculations are blocked.
     */
    public function isLocked(CommercialCampaign $campaign): bool
    {
        return in_array($campaign->status, self::LOCKED_STATUSES, true);
    }

    /**
     * True if campaign can currently be dispatched.
     */
    public function canDispatch(CommercialCampaign $campaign): bool
    {
        return $campaign->status === 'ready' && $campaign->hasAudience();
    }

    /* ── Logging helper ──────────────────────────────────────────── */

    public function log(
        CommercialCampaignDelivery $delivery,
        string $level,
        string $message,
        array $context = []
    ): void {
        CommercialCampaignDeliveryLog::create([
            'commercial_campaign_delivery_id' => $delivery->id,
            'level' => $level,
            'message' => $message,
            'context_json' => ! empty($context) ? $context : null,
        ]);
    }

    protected function resolveWhatsAppProvider(): string
    {
        try {
            $configuredDriver = (string) core()->getConfigData('whatsapp.provider.driver');
        } catch (\Throwable) {
            $configuredDriver = '';
        }

        if ($configuredDriver === '') {
            $configuredDriver = (string) config('commercial.campaign.whatsapp_provider', 'waha');
        }

        return match ($configuredDriver) {
            'meta', 'meta_official' => 'meta_official',
            'evolution'             => 'evolution',
            default                 => 'waha',
        };
    }

    /* ── Private ─────────────────────────────────────────────────── */

    protected function getSender(string $channel): CampaignChannelSenderInterface
    {
        return match ($channel) {
            'email' => $this->emailSender,
            'whatsapp' => $this->whatsappSender,
            default => throw new \RuntimeException("Unsupported channel: {$channel}"),
        };
    }
}
