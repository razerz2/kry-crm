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
 * Step 1 of the campaign sending pipeline.
 *
 * Responsibilities:
 *  1. Create all delivery rows from the frozen audience (idempotent).
 *  2. Mark pending deliveries as "queued".
 *  3. Dispatch one SendCommercialCampaignDeliveryJob per queued delivery.
 *
 * A campaign that has all audience members as "skipped" (no contact info)
 * is handled gracefully — final status is computed at the end of the last
 * delivery job.
 */
class DispatchCommercialCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $campaignId) {}

    public function handle(CommercialCampaignDeliveryService $deliveryService): void
    {
        $campaign = CommercialCampaign::find($this->campaignId);

        if (! $campaign) {
            Log::warning("[DispatchCommercialCampaignJob] Campaign #{$this->campaignId} not found.");

            return;
        }

        if ($campaign->status !== 'sending') {
            Log::info("[DispatchCommercialCampaignJob] Campaign #{$this->campaignId} is not in 'sending' state ({$campaign->status}). Aborting.");

            return;
        }

        Log::info("[DispatchCommercialCampaignJob] Creating delivery rows for campaign #{$this->campaignId}.");

        // 1. Create delivery rows (idempotent – insertOrIgnore)
        $total = $deliveryService->createDeliveries($campaign);

        Log::info("[DispatchCommercialCampaignJob] {$total} delivery rows for campaign #{$this->campaignId}. Queuing send jobs.");

        // 2. Queue individual send jobs for pending rows
        $queue = config('commercial.campaign.queue', 'default');
        $chunkSize = (int) config('commercial.campaign.delivery_chunk_size', 100);

        CommercialCampaignDelivery::where('commercial_campaign_id', $this->campaignId)
            ->where('status', 'pending')
            ->chunkById($chunkSize, function ($deliveries) use ($queue) {
                $now = now()->toDateTimeString();

                // Bulk-update to queued
                $ids = $deliveries->pluck('id')->all();
                CommercialCampaignDelivery::whereIn('id', $ids)
                    ->update(['status' => 'queued', 'queued_at' => $now]);

                // Dispatch individual jobs
                foreach ($deliveries as $delivery) {
                    SendCommercialCampaignDeliveryJob::dispatch($delivery->id)->onQueue($queue);
                }
            });

        // 3. If every row is already terminal (all skipped), finalize immediately
        $inProgress = CommercialCampaignDelivery::where('commercial_campaign_id', $this->campaignId)
            ->whereIn('status', ['pending', 'queued', 'sending'])
            ->count();

        if ($inProgress === 0) {
            Log::info("[DispatchCommercialCampaignJob] All deliveries are terminal for campaign #{$this->campaignId}. Finalizing.");
            $deliveryService->updateCampaignFinalStatus($campaign->fresh());
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("[DispatchCommercialCampaignJob] Job failed for campaign #{$this->campaignId}: {$exception->getMessage()}");

        $campaign = CommercialCampaign::find($this->campaignId);
        if ($campaign && $campaign->status === 'sending') {
            $campaign->update(['status' => 'failed']);
        }
    }
}
