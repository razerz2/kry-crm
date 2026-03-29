<?php

namespace Webkul\Commercial\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Webkul\Commercial\Models\CommercialCampaign;
use Webkul\Commercial\Models\CommercialCampaignDelivery;
use Webkul\Commercial\Services\CommercialCampaignDeliveryService;

/**
 * Step 2 of the campaign sending pipeline.
 *
 * Processes a single delivery: picks the correct channel sender,
 * updates status, records logs, and — after the last delivery for its
 * campaign — triggers final-status computation.
 */
class SendCommercialCampaignDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of attempts before marking the delivery as failed via failed().
     */
    public int $tries = 3;

    /**
     * Seconds to wait between retries (exponential via backoff array).
     */
    public array $backoff = [30, 120, 300];

    public function __construct(public readonly int $deliveryId) {}

    public function handle(CommercialCampaignDeliveryService $deliveryService): void
    {
        $delivery = CommercialCampaignDelivery::find($this->deliveryId);

        if (! $delivery) {
            Log::warning("[SendCommercialCampaignDeliveryJob] Delivery #{$this->deliveryId} not found.");

            return;
        }

        // Process (send or record skip/failure)
        $deliveryService->processDelivery($delivery);

        // After processing, check if this was the last in-progress delivery
        $this->maybeFinalizeCampaign($delivery->commercial_campaign_id, $deliveryService);
    }

    protected function maybeFinalizeCampaign(int $campaignId, CommercialCampaignDeliveryService $deliveryService): void
    {
        $inProgress = CommercialCampaignDelivery::where('commercial_campaign_id', $campaignId)
            ->whereIn('status', ['pending', 'queued', 'sending'])
            ->count();

        if ($inProgress > 0) {
            return; // Other jobs still running
        }

        $campaign = CommercialCampaign::find($campaignId);
        if ($campaign && $campaign->status === 'sending') {
            Log::info("[SendCommercialCampaignDeliveryJob] All deliveries done for campaign #{$campaignId}. Finalizing.");
            $deliveryService->updateCampaignFinalStatus($campaign);
        }
    }

    /**
     * Called by the queue worker after all retry attempts are exhausted.
     * Ensures the delivery is set to "failed" even if processDelivery
     * did not update it (e.g. exception before status update).
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("[SendCommercialCampaignDeliveryJob] Delivery #{$this->deliveryId} failed after retries: {$exception->getMessage()}");

        $delivery = CommercialCampaignDelivery::find($this->deliveryId);
        if ($delivery && ! $delivery->isFinished()) {
            $delivery->update([
                'status' => 'failed',
                'failure_reason' => mb_substr('Queue retries exhausted: '.$exception->getMessage(), 0, 500),
                'failed_at' => now(),
            ]);

            // Re-check campaign finalization
            $inProgress = CommercialCampaignDelivery::where('commercial_campaign_id', $delivery->commercial_campaign_id)
                ->whereIn('status', ['pending', 'queued', 'sending'])
                ->count();

            if ($inProgress === 0) {
                $campaign = CommercialCampaign::find($delivery->commercial_campaign_id);
                if ($campaign && $campaign->status === 'sending') {
                    app(CommercialCampaignDeliveryService::class)->updateCampaignFinalStatus($campaign);
                }
            }
        }
    }
}
