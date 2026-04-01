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
use Webkul\Commercial\Services\CampaignScheduleService;
use Webkul\Commercial\Services\CommercialCampaignDeliveryService;

/**
 * Step 1 of the delivery pipeline for one campaign run.
 */
class DispatchCommercialCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $campaignId,
        public readonly ?int $campaignRunId = null,
    ) {}

    public function handle(
        CommercialCampaignDeliveryService $deliveryService,
        CampaignScheduleService $scheduleService
    ): void {
        $campaign = CommercialCampaign::find($this->campaignId);

        if (! $campaign) {
            Log::warning("[DispatchCommercialCampaignJob] Campaign #{$this->campaignId} not found.");

            return;
        }

        if (! in_array($campaign->status, ['running', 'sending'], true)) {
            Log::info("[DispatchCommercialCampaignJob] Campaign #{$this->campaignId} is not in dispatch state ({$campaign->status}).");

            return;
        }

        $deliveryService->createDeliveries($campaign, $this->campaignRunId);

        $queue = config('commercial.campaign.queue', 'default');
        $chunkSize = (int) config('commercial.campaign.delivery_chunk_size', 100);

        CommercialCampaignDelivery::where('commercial_campaign_id', $this->campaignId)
            ->when(
                $this->campaignRunId !== null,
                fn ($query) => $query->where('commercial_campaign_run_id', $this->campaignRunId)
            )
            ->where('status', 'pending')
            ->chunkById($chunkSize, function ($deliveries) use ($queue) {
                $now = now()->toDateTimeString();
                $ids = $deliveries->pluck('id')->all();

                CommercialCampaignDelivery::whereIn('id', $ids)
                    ->update(['status' => 'queued', 'queued_at' => $now]);

                foreach ($deliveries as $delivery) {
                    SendCommercialCampaignDeliveryJob::dispatch($delivery->id, $this->campaignRunId)
                        ->onQueue($queue);
                }
            });

        $inProgress = CommercialCampaignDelivery::where('commercial_campaign_id', $this->campaignId)
            ->when(
                $this->campaignRunId !== null,
                fn ($query) => $query->where('commercial_campaign_run_id', $this->campaignRunId)
            )
            ->whereIn('status', ['pending', 'queued', 'sending'])
            ->count();

        if ($inProgress === 0) {
            if ($this->campaignRunId !== null) {
                $scheduleService->finalizeRun($this->campaignId, $this->campaignRunId);
            } else {
                $deliveryService->updateCampaignFinalStatus($campaign->fresh());
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("[DispatchCommercialCampaignJob] Failed for campaign #{$this->campaignId}: {$exception->getMessage()}");

        if ($this->campaignRunId !== null) {
            app(CampaignScheduleService::class)->failRun(
                $this->campaignId,
                $this->campaignRunId,
                $exception->getMessage()
            );

            return;
        }

        $campaign = CommercialCampaign::find($this->campaignId);
        if ($campaign && in_array($campaign->status, ['running', 'sending'], true)) {
            $campaign->update(['status' => 'failed', 'next_run_at' => null]);
        }
    }
}

