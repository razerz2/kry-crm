<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_campaign_runs', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('commercial_campaign_id');
            $table->foreign('commercial_campaign_id', 'ccr_campaign_fk')
                ->references('id')
                ->on('commercial_campaigns')
                ->onDelete('cascade');

            $table->string('trigger_type')->default('scheduler'); // scheduler | manual
            $table->string('status')->default('queued'); // queued | running | completed | failed | canceled
            $table->dateTime('scheduled_for')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();

            $table->unsignedInteger('audience_total')->default(0);
            $table->unsignedInteger('audience_with_email')->default(0);
            $table->unsignedInteger('audience_with_phone')->default(0);
            $table->unsignedInteger('total_deliveries')->default(0);
            $table->unsignedInteger('total_sent')->default(0);
            $table->unsignedInteger('total_failed')->default(0);
            $table->unsignedInteger('total_skipped')->default(0);
            $table->unsignedInteger('total_canceled')->default(0);

            $table->text('error_message')->nullable();
            $table->json('meta_json')->nullable();

            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by', 'ccr_created_by_fk')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->timestamps();

            $table->index(['commercial_campaign_id', 'status'], 'ccr_campaign_status_idx');
            $table->index(['commercial_campaign_id', 'scheduled_for'], 'ccr_campaign_schedule_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_campaign_runs');
    }
};
