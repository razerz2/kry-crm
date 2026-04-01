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

class SendCommercialCampaignDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly int $deliveryId,
        public readonly ?int $campaignRunId = null,
    ) {}

    public function handle(
        CommercialCampaignDeliveryService $deliveryService,
        CampaignScheduleService $scheduleService
    ): void {
        $delivery = CommercialCampaignDelivery::find($this->deliveryId);

        if (! $delivery) {
            Log::warning("[SendCommercialCampaignDeliveryJob] Delivery #{$this->deliveryId} not found.");

            return;
        }

        $deliveryService->processDelivery($delivery);

        $this->maybeFinalizeCampaign(
            $delivery->commercial_campaign_id,
            $scheduleService,
            $deliveryService
        );
    }

    protected function maybeFinalizeCampaign(
        int $campaignId,
        CampaignScheduleService $scheduleService,
        CommercialCampaignDeliveryService $deliveryService
    ): void {
        $inProgressQuery = CommercialCampaignDelivery::where('commercial_campaign_id', $campaignId)
            ->whereIn('status', ['pending', 'queued', 'sending']);

        if ($this->campaignRunId !== null) {
            $inProgressQuery->where('commercial_campaign_run_id', $this->campaignRunId);
        }

        if ($inProgressQuery->count() > 0) {
            return;
        }

        if ($this->campaignRunId !== null) {
            $scheduleService->finalizeRun($campaignId, $this->campaignRunId);

            return;
        }

        $campaign = CommercialCampaign::find($campaignId);
        if ($campaign && in_array($campaign->status, ['running', 'sending'], true)) {
            $deliveryService->updateCampaignFinalStatus($campaign);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("[SendCommercialCampaignDeliveryJob] Delivery #{$this->deliveryId} failed: {$exception->getMessage()}");

        $delivery = CommercialCampaignDelivery::find($this->deliveryId);

        if ($delivery && ! $delivery->isFinished()) {
            $delivery->update([
                'status' => 'failed',
                'failure_reason' => mb_substr('Queue retries exhausted: '.$exception->getMessage(), 0, 500),
                'failed_at' => now(),
            ]);
        }

        if ($this->campaignRunId !== null) {
            app(CampaignScheduleService::class)->finalizeRun(
                $delivery?->commercial_campaign_id ?? 0,
                $this->campaignRunId
            );

            return;
        }

        if ($delivery) {
            $campaign = CommercialCampaign::find($delivery->commercial_campaign_id);
            if ($campaign && in_array($campaign->status, ['running', 'sending'], true)) {
                app(CommercialCampaignDeliveryService::class)->updateCampaignFinalStatus($campaign);
            }
        }
    }
}
