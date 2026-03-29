<?php

namespace Webkul\Commercial\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Commercial\Enums\AccountProductStatus;
use Webkul\Commercial\Services\Audience\AudienceFilter;
use Webkul\Commercial\Services\Audience\CommercialAudienceService;

class AudiencePreviewCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'commercial:audience-preview
        {--entity-type=both : Entity type: person, organization, both}
        {--product= : CRM product IDs (comma-separated)}
        {--status= : Commercial statuses (comma-separated)}
        {--segment= : Segment: customer_any, non_customer, has_link, no_link}
        {--channel= : Channel: email, whatsapp, both}
        {--only-email : Only entities with valid email}
        {--only-phone : Only entities with valid phone}
        {--search= : Free-text search by name/email/phone}
        {--limit=20 : Max records to display}
        {--stats-only : Show only stats, no individual records}';

    /**
     * The console command description.
     */
    protected $description = 'Preview the commercial audience based on filters (Etapa 6)';

    public function __construct(
        protected CommercialAudienceService $audienceService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $filter = $this->buildFilter();

        $this->info('Building audience...');
        $this->newLine();

        $result = $this->audienceService->forCampaignPreview($filter);
        $items = $result['items'];
        $stats = $result['stats'];

        // Display stats
        $this->displayStats($stats);

        // Display sample records
        if (! $this->option('stats-only') && $items->isNotEmpty()) {
            $this->newLine();
            $this->displayRecords($items);
        }

        return self::SUCCESS;
    }

    /**
     * Build AudienceFilter from command options.
     */
    protected function buildFilter(): AudienceFilter
    {
        $productIds = $this->option('product')
            ? array_map('intval', explode(',', $this->option('product')))
            : [];

        $statuses = $this->option('status')
            ? explode(',', $this->option('status'))
            : [];

        return new AudienceFilter(
            entityType: $this->option('entity-type'),
            crmProductIds: $productIds,
            commercialStatuses: $statuses,
            segment: $this->option('segment') ?: null,
            channel: $this->option('channel') ?: null,
            onlyWithEmail: (bool) $this->option('only-email'),
            onlyWithPhone: (bool) $this->option('only-phone'),
            search: $this->option('search') ?: null,
            limit: (int) $this->option('limit'),
        );
    }

    /**
     * Display audience statistics.
     */
    protected function displayStats(array $stats): void
    {
        $this->components->twoColumnDetail('<fg=cyan>Total</>', (string) $stats['total']);
        $this->components->twoColumnDetail('<fg=cyan>With Email</>', (string) $stats['with_email']);
        $this->components->twoColumnDetail('<fg=cyan>With Phone</>', (string) $stats['with_phone']);
        $this->components->twoColumnDetail('<fg=cyan>Persons</>', (string) $stats['persons']);
        $this->components->twoColumnDetail('<fg=cyan>Organizations</>', (string) $stats['organizations']);

        if (! empty($stats['by_status'])) {
            $this->newLine();
            $this->info('By Status:');

            foreach ($stats['by_status'] as $status => $count) {
                $label = AccountProductStatus::tryFrom($status)?->label() ?? $status;
                $this->components->twoColumnDetail("  {$label}", (string) $count);
            }
        }

        if (! empty($stats['by_product'])) {
            $this->newLine();
            $this->info('By Product:');

            foreach ($stats['by_product'] as $product => $count) {
                $this->components->twoColumnDetail("  {$product}", (string) $count);
            }
        }
    }

    /**
     * Display audience records in a table.
     */
    protected function displayRecords($items): void
    {
        $this->info('Sample Records:');

        $rows = $items->map(function ($item) {
            return [
                $item->entityLabel,
                $item->entityId,
                mb_substr($item->displayName, 0, 30),
                mb_substr($item->organizationName ?? '-', 0, 25),
                mb_substr($item->email ?? '-', 0, 30),
                mb_substr($item->phone ?? '-', 0, 18),
                implode(', ', $item->availableChannels) ?: '-',
                mb_substr($item->sourceSummary ?: '-', 0, 40),
            ];
        })->toArray();

        $this->table(
            ['Type', 'ID', 'Name', 'Organization', 'Email', 'Phone', 'Channels', 'Commercial'],
            $rows
        );
    }
}
