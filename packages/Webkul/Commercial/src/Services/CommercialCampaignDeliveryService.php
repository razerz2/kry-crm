<?php

namespace Webkul\Commercial\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Webkul\Commercial\Jobs\DispatchCommercialCampaignJob;
use Webkul\Commercial\Models\CommercialCampaign;
use Webkul\Commercial\Models\CommercialCampaignAudience;
use Webkul\Commercial\Models\CommercialCampaignDelivery;
use Webkul\Commercial\Models\CommercialCampaignDeliveryLog;
use Webkul\Commercial\Repositories\CommercialCampaignDeliveryRepository;
use Webkul\Commercial\Services\Sending\CampaignChannelSenderInterface;
use Webkul\Commercial\Services\Sending\EmailCampaignSender;
use Webkul\Commercial\Services\Sending\WhatsAppCampaignSender;
use Webkul\Commercial\Services\CommercialCampaignMetricsService;
use Webkul\Commercial\Services\Template\CommercialCampaignTemplateRenderer;
use Webkul\Commercial\Services\Template\TemplateRenderContext;

class CommercialCampaignDeliveryService
{
    /**
     * Statuses that lock a campaign from further edits.
     */
    public const LOCKED_STATUSES = ['sending', 'sent', 'partially_sent', 'failed'];

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
     * Sets campaign status to "sending" and dispatches the background job.
     * Does NOT create delivery rows synchronously.
     *
     * @throws \RuntimeException if campaign is not dispatchable
     */
    public function dispatch(CommercialCampaign $campaign): void
    {
        if ($campaign->status !== 'ready') {
            throw new \RuntimeException(
                "Campaign #{$campaign->id} must be in 'ready' status to dispatch (current: {$campaign->status})."
            );
        }

        if (! $campaign->hasAudience()) {
            throw new \RuntimeException(
                "Campaign #{$campaign->id} has no frozen audience. Freeze the audience before dispatching."
            );
        }

        $campaign->update([
            'status'       => 'sending',
            'dispatched_at'=> now(),
            'updated_by'   => Auth::id(),
        ]);

        DispatchCommercialCampaignJob::dispatch($campaign->id)
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
    public function createDeliveries(CommercialCampaign $campaign): int
    {
        $campaignChannel = $campaign->channel;
        $insertedTotal   = 0;
        $chunkSize       = (int) config('commercial.campaign.delivery_chunk_size', 200);

        $campaign->audienceMembers()
            ->chunkById($chunkSize, function ($members) use ($campaign, $campaignChannel, &$insertedTotal) {
                $rows = [];

                foreach ($members as $member) {
                    foreach ($this->buildDeliveryRows($campaign, $member, $campaignChannel) as $row) {
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
        string $campaignChannel
    ): array {
        $rows = [];
        $now  = now()->toDateTimeString();
        $base = [
            'commercial_campaign_id'          => $campaign->id,
            'commercial_campaign_audience_id' => $member->id,
            'entity_type'                     => $member->entity_type,
            'entity_id'                       => $member->entity_id,
            'recipient_name'                  => $member->display_name,
            'created_by'                      => $campaign->updated_by,
            'created_at'                      => $now,
            'updated_at'                      => $now,
        ];

        if (in_array($campaignChannel, ['email', 'both'], true)) {
            if ($member->email) {
                $ctx = TemplateRenderContext::fromAudienceMember($member, $campaign, 'email');
                $rows[] = array_merge($base, [
                    'channel'          => 'email',
                    'provider'         => config('commercial.campaign.email_provider', 'internal_email'),
                    'recipient_email'  => $member->email,
                    'subject'          => $this->renderer->renderSubject($campaign->subject ?? '', $ctx),
                    'rendered_message' => $this->renderer->renderBody($campaign->message_body ?? '', $ctx),
                    'status'           => 'pending',
                ]);
            } else {
                $rows[] = array_merge($base, [
                    'channel'          => 'email',
                    'provider'         => config('commercial.campaign.email_provider', 'internal_email'),
                    'rendered_message' => '',
                    'status'           => 'skipped',
                    'failure_reason'   => 'No email address',
                ]);
            }
        }

        if (in_array($campaignChannel, ['whatsapp', 'both'], true)) {
            if ($member->phone) {
                $ctx = TemplateRenderContext::fromAudienceMember($member, $campaign, 'whatsapp');
                $rows[] = array_merge($base, [
                    'channel'          => 'whatsapp',
                    'provider'         => config('commercial.campaign.whatsapp_provider', 'waha'),
                    'recipient_phone'  => $member->phone,
                    'rendered_message' => $this->renderer->renderBody($campaign->message_body ?? '', $ctx),
                    'status'           => 'pending',
                ]);
            } else {
                $rows[] = array_merge($base, [
                    'channel'          => 'whatsapp',
                    'provider'         => config('commercial.campaign.whatsapp_provider', 'waha'),
                    'rendered_message' => '',
                    'status'           => 'skipped',
                    'failure_reason'   => 'No phone number',
                ]);
            }
        }

        return $rows;
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
                'status'  => 'sent',
                'sent_at' => now(),
            ]);

            $this->log($delivery, 'info', 'Delivery sent successfully.');

        } catch (\Throwable $e) {
            $delivery->update([
                'status'         => 'failed',
                'failure_reason' => mb_substr($e->getMessage(), 0, 500),
                'failed_at'      => now(),
            ]);

            $this->log($delivery, 'error', 'Delivery failed: ' . $e->getMessage(), [
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
            'status'  => $finalStatus,
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
            'level'        => $level,
            'message'      => $message,
            'context_json' => ! empty($context) ? $context : null,
        ]);
    }

    /* ── Private ─────────────────────────────────────────────────── */

    protected function getSender(string $channel): CampaignChannelSenderInterface
    {
        return match ($channel) {
            'email'    => $this->emailSender,
            'whatsapp' => $this->whatsappSender,
            default    => throw new \RuntimeException("Unsupported channel: {$channel}"),
        };
    }
}
