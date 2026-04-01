<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commercial_campaign_deliveries', function (Blueprint $table) {
            $table->unsignedBigInteger('commercial_campaign_run_id')->nullable()->after('commercial_campaign_id');

            $table->foreign('commercial_campaign_run_id', 'ccd_run_fk')
                ->references('id')
                ->on('commercial_campaign_runs')
                ->onDelete('set null');

            $table->index(['commercial_campaign_run_id', 'status'], 'ccd_run_status_idx');

            $table->dropUnique('ccd_unique_delivery');

            $table->unique(
                ['commercial_campaign_id', 'commercial_campaign_run_id', 'commercial_campaign_audience_id', 'channel'],
                'ccd_unique_delivery_run'
            );
        });
    }

    public function down(): void
    {
        Schema::table('commercial_campaign_deliveries', function (Blueprint $table) {
            $table->dropUnique('ccd_unique_delivery_run');
            $table->dropIndex('ccd_run_status_idx');
            $table->dropForeign('ccd_run_fk');
            $table->dropColumn('commercial_campaign_run_id');

            $table->unique(
                ['commercial_campaign_id', 'commercial_campaign_audience_id', 'channel'],
                'ccd_unique_delivery'
            );
        });
    }
};
