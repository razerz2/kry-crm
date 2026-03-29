<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fill in the missing per-status aggregated counters on the campaigns table.
        // total_sent / total_failed / total_skipped already exist from step 8.
        Schema::table('commercial_campaigns', function (Blueprint $table) {
            $table->unsignedInteger('total_pending')->default(0)->after('total_skipped');
            $table->unsignedInteger('total_queued')->default(0)->after('total_pending');
            $table->unsignedInteger('total_sending')->default(0)->after('total_queued');
            $table->unsignedInteger('total_canceled')->default(0)->after('total_sending');
        });

        // Add search/filter indexes to deliveries that were missing.
        Schema::table('commercial_campaign_deliveries', function (Blueprint $table) {
            $table->index('recipient_name', 'ccd_name_idx');
            $table->index('provider', 'ccd_provider_idx');
        });
    }

    public function down(): void
    {
        Schema::table('commercial_campaign_deliveries', function (Blueprint $table) {
            $table->dropIndex('ccd_name_idx');
            $table->dropIndex('ccd_provider_idx');
        });

        Schema::table('commercial_campaigns', function (Blueprint $table) {
            $table->dropColumn(['total_pending', 'total_queued', 'total_sending', 'total_canceled']);
        });
    }
};
