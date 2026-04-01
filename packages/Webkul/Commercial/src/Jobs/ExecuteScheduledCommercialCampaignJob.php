<?php

namespace Webkul\Commercial\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Webkul\Commercial\Models\CommercialCampaign;
use Webkul\Commercial\Models\CommercialCampaignRun;
use Webkul\Commercial\Services\CampaignScheduleService;
use Webkul\Commercial\Services\CommercialCampaignDeliveryService;
use Webkul\Commercial\Services\CommercialCampaignService;

class ExecuteScheduledCommercialCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $campaignId,
        public readonly string $triggerType = 'scheduler',
        public readonly bool $force = false,
        public readonly ?int $triggeredBy = null,
    ) {}

    public function handle(
        CampaignScheduleService $scheduleService,
        CommercialCampaignService $campaignService,
        CommercialCampaignDeliveryService $deliveryService
    ): void {
        $lock = Cache::lock("commercial-campaign-execute:{$this->campaignId}", 120);

        if (! $lock->get()) {
            return;
        }

        /** @var CommercialCampaignRun|null $run */
        $run = null;

        try {
            $run = $scheduleService->startExecutionRun(
                $this->campaignId,
                $this->triggerType,
                $this->force,
                $this->triggeredBy
            );

            if (! $run) {
                return;
            }

            $campaign = CommercialCampaign::find($this->campaignId);
            if (! $campaign) {
                $scheduleService->failRun($this->campaignId, $run->id, 'Campaign not found after run start.');

                return;
            }

            // For recurring flows, the audience is refreshed on every execution run.
            $campaignService->freezeAudienceForExecution($campaign);
            $campaign = $campaign->fresh();

            $scheduleService->syncRunAudienceSnapshot($run, $campaign);

            if (! $campaign->hasAudience()) {
                $scheduleService->failRun($campaign->id, $run->id, 'No audience generated for this run.');

                return;
            }

            if (blank($campaign->message_body)) {
                $scheduleService->failRun($campaign->id, $run->id, 'Campaign message body is empty.');

                return;
            }

            if (in_array($campaign->channel, ['email', 'both'], true) && blank($campaign->subject)) {
                $scheduleService->failRun($campaign->id, $run->id, 'Campaign subject is required for email channel.');

                return;
            }

            $deliveryService->dispatch($campaign, $run->id, false);

        } catch (\Throwable $e) {
            Log::error('[ExecuteScheduledCommercialCampaignJob] '.$e->getMessage(), [
                'campaign_id' => $this->campaignId,
                'run_id' => $run?->id,
                'trigger' => $this->triggerType,
                'exception' => get_class($e),
            ]);

            if ($run) {
                $scheduleService->failRun($this->campaignId, $run->id, $e->getMessage());
            }
        } finally {
            $lock->release();
        }
    }
}
