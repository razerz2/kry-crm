<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * New statuses: sending | sent | partially_sent | failed
     * (column is already string/no enum — no column change needed for status)
     *
     * New timestamp + counter fields to track dispatch lifecycle.
     */
    public function up(): void
    {
        Schema::table('commercial_campaigns', function (Blueprint $table) {
            $table->datetime('dispatched_at')->nullable()->after('audience_generated_at');
            $table->datetime('sent_at')->nullable()->after('dispatched_at');

            $table->unsignedInteger('total_deliveries')->default(0)->after('total_with_phone');
            $table->unsignedInteger('total_sent')->default(0)->after('total_deliveries');
            $table->unsignedInteger('total_failed')->default(0)->after('total_sent');
            $table->unsignedInteger('total_skipped')->default(0)->after('total_failed');
        });
    }

    public function down(): void
    {
        Schema::table('commercial_campaigns', function (Blueprint $table) {
            $table->dropColumn([
                'dispatched_at',
                'sent_at',
                'total_deliveries',
                'total_sent',
                'total_failed',
                'total_skipped',
            ]);
        });
    }
};
