<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('commercial_campaign_deliveries', 'normalized_destination')) {
            Schema::table('commercial_campaign_deliveries', function (Blueprint $table) {
                $table->string('normalized_destination', 191)
                    ->nullable()
                    ->after('recipient_phone');

                $table->index(
                    ['commercial_campaign_run_id', 'channel', 'normalized_destination'],
                    'ccd_run_channel_normalized_idx'
                );
            });
        }

        $this->backfillNormalizedDestinations();
        $this->deduplicateByEntityAndChannelPerRun();
        $this->deduplicateByNormalizedDestinationPerRun();

        Schema::table('commercial_campaign_deliveries', function (Blueprint $table) {
            if (! $this->indexExists('commercial_campaign_deliveries', 'ccd_unique_run_entity_channel')) {
                $table->unique(
                    ['commercial_campaign_run_id', 'entity_type', 'entity_id', 'channel'],
                    'ccd_unique_run_entity_channel'
                );
            }

            if (! $this->indexExists('commercial_campaign_deliveries', 'ccd_unique_run_channel_destination')) {
                $table->unique(
                    ['commercial_campaign_run_id', 'channel', 'normalized_destination'],
                    'ccd_unique_run_channel_destination'
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table('commercial_campaign_deliveries', function (Blueprint $table) {
            if ($this->indexExists('commercial_campaign_deliveries', 'ccd_unique_run_channel_destination')) {
                $table->dropUnique('ccd_unique_run_channel_destination');
            }

            if ($this->indexExists('commercial_campaign_deliveries', 'ccd_unique_run_entity_channel')) {
                $table->dropUnique('ccd_unique_run_entity_channel');
            }

            if ($this->indexExists('commercial_campaign_deliveries', 'ccd_run_channel_normalized_idx')) {
                $table->dropIndex('ccd_run_channel_normalized_idx');
            }

            if (Schema::hasColumn('commercial_campaign_deliveries', 'normalized_destination')) {
                $table->dropColumn('normalized_destination');
            }
        });
    }

    protected function backfillNormalizedDestinations(): void
    {
        DB::table('commercial_campaign_deliveries')
            ->select(['id', 'channel', 'recipient_email', 'recipient_phone'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $normalized = $this->normalizeDestination(
                        (string) $row->channel,
                        $row->channel === 'email' ? $row->recipient_email : $row->recipient_phone
                    );

                    DB::table('commercial_campaign_deliveries')
                        ->where('id', $row->id)
                        ->update(['normalized_destination' => $normalized]);
                }
            }, 'id');
    }

    protected function deduplicateByEntityAndChannelPerRun(): void
    {
        $groups = DB::table('commercial_campaign_deliveries')
            ->select([
                'commercial_campaign_run_id',
                'entity_type',
                'entity_id',
                'channel',
                DB::raw('COUNT(*) as total'),
            ])
            ->whereNotNull('commercial_campaign_run_id')
            ->groupBy('commercial_campaign_run_id', 'entity_type', 'entity_id', 'channel')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            $rows = DB::table('commercial_campaign_deliveries')
                ->where('commercial_campaign_run_id', $group->commercial_campaign_run_id)
                ->where('entity_type', $group->entity_type)
                ->where('entity_id', $group->entity_id)
                ->where('channel', $group->channel)
                ->orderBy('id')
                ->get();

            $keepId = $this->pickPreferredDeliveryId($rows);

            DB::table('commercial_campaign_deliveries')
                ->where('commercial_campaign_run_id', $group->commercial_campaign_run_id)
                ->where('entity_type', $group->entity_type)
                ->where('entity_id', $group->entity_id)
                ->where('channel', $group->channel)
                ->where('id', '!=', $keepId)
                ->delete();
        }
    }

    protected function deduplicateByNormalizedDestinationPerRun(): void
    {
        $groups = DB::table('commercial_campaign_deliveries')
            ->select([
                'commercial_campaign_run_id',
                'channel',
                'normalized_destination',
                DB::raw('COUNT(*) as total'),
            ])
            ->whereNotNull('commercial_campaign_run_id')
            ->whereNotNull('normalized_destination')
            ->where('normalized_destination', '!=', '')
            ->groupBy('commercial_campaign_run_id', 'channel', 'normalized_destination')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            $rows = DB::table('commercial_campaign_deliveries')
                ->where('commercial_campaign_run_id', $group->commercial_campaign_run_id)
                ->where('channel', $group->channel)
                ->where('normalized_destination', $group->normalized_destination)
                ->orderBy('id')
                ->get();

            $keepId = $this->pickPreferredDeliveryId($rows);

            DB::table('commercial_campaign_deliveries')
                ->where('commercial_campaign_run_id', $group->commercial_campaign_run_id)
                ->where('channel', $group->channel)
                ->where('normalized_destination', $group->normalized_destination)
                ->where('id', '!=', $keepId)
                ->delete();
        }
    }

    protected function pickPreferredDeliveryId($rows): int
    {
        $statusPriority = [
            'sent' => 0,
            'sending' => 1,
            'queued' => 2,
            'pending' => 3,
            'failed' => 4,
            'skipped' => 5,
            'canceled' => 6,
        ];

        $sorted = $rows->sort(function ($a, $b) use ($statusPriority) {
            $aHasDestination = ! empty($a->normalized_destination) ? 1 : 0;
            $bHasDestination = ! empty($b->normalized_destination) ? 1 : 0;

            if ($aHasDestination !== $bHasDestination) {
                return $bHasDestination <=> $aHasDestination;
            }

            $aPriority = $statusPriority[$a->status] ?? 99;
            $bPriority = $statusPriority[$b->status] ?? 99;

            if ($aPriority !== $bPriority) {
                return $aPriority <=> $bPriority;
            }

            return $a->id <=> $b->id;
        });

        return (int) $sorted->first()->id;
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

    protected function indexExists(string $table, string $index): bool
    {
        $result = DB::select(
            'SHOW INDEX FROM '.$table.' WHERE Key_name = ?',
            [$index]
        );

        return ! empty($result);
    }
};
