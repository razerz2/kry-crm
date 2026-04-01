<?php

namespace Webkul\Commercial\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Commercial\Services\CampaignScheduleService;

class CampaignScheduleScanCommand extends Command
{
    protected $signature = 'commercial:campaigns:scan {--limit=25 : Maximum due campaigns to enqueue}';

    protected $description = 'Scan due commercial campaigns and enqueue automatic executions.';

    public function handle(CampaignScheduleService $scheduleService): int
    {
        $limit = (int) $this->option('limit');
        $limit = $limit > 0 ? $limit : (int) config('commercial.campaign.scan_limit', 25);

        $enqueued = $scheduleService->enqueueDueCampaigns($limit);

        $this->info("Commercial campaign scan completed. Enqueued runs: {$enqueued}.");

        return self::SUCCESS;
    }
}
