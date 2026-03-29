<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_campaign_deliveries', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('commercial_campaign_id');
            $table->foreign('commercial_campaign_id', 'ccd_campaign_fk')
                ->references('id')
                ->on('commercial_campaigns')
                ->onDelete('cascade');

            $table->unsignedBigInteger('commercial_campaign_audience_id')->nullable();
            $table->foreign('commercial_campaign_audience_id', 'ccd_audience_fk')
                ->references('id')
                ->on('commercial_campaign_audience')
                ->onDelete('set null');

            $table->string('entity_type');
            $table->unsignedInteger('entity_id');

            // email | whatsapp
            $table->string('channel');

            // internal_email | meta_official | waha | evolution
            $table->string('provider')->nullable();

            $table->string('recipient_name')->nullable();
            $table->string('recipient_email')->nullable();
            $table->string('recipient_phone')->nullable();

            $table->string('subject')->nullable();
            $table->longText('rendered_message')->nullable();

            // pending | queued | sending | sent | failed | skipped | canceled
            $table->string('status')->default('pending');
            $table->text('failure_reason')->nullable();
            $table->string('provider_message_id')->nullable();

            $table->datetime('queued_at')->nullable();
            $table->datetime('sent_at')->nullable();
            $table->datetime('failed_at')->nullable();

            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by', 'ccd_created_by_fk')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->timestamps();

            // Prevent duplicate deliveries for same audience member + channel
            $table->unique(
                ['commercial_campaign_id', 'commercial_campaign_audience_id', 'channel'],
                'ccd_unique_delivery'
            );

            $table->index(['commercial_campaign_id', 'status'], 'ccd_campaign_status_idx');
            $table->index(['commercial_campaign_id', 'channel'], 'ccd_campaign_channel_idx');
            $table->index('recipient_email', 'ccd_email_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_campaign_deliveries');
    }
};
