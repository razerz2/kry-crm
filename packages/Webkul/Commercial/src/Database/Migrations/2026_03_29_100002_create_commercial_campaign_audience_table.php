<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('commercial_campaign_audience', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('commercial_campaign_id');
            $table->foreign('commercial_campaign_id', 'cca_campaign_fk')
                ->references('id')
                ->on('commercial_campaigns')
                ->onDelete('cascade');

            $table->string('entity_type');
            $table->unsignedInteger('entity_id');
            $table->string('display_name');
            $table->string('organization_name')->nullable();

            $table->unsignedInteger('primary_contact_person_id')->nullable();
            $table->string('primary_contact_name')->nullable();

            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            $table->json('available_channels')->nullable();
            $table->json('crm_products')->nullable();
            $table->json('commercial_statuses')->nullable();
            $table->text('source_summary')->nullable();
            $table->json('payload_json')->nullable();

            $table->timestamps();

            $table->index(['commercial_campaign_id', 'entity_type', 'entity_id'], 'cca_campaign_entity_idx');
            $table->index('email', 'cca_email_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commercial_campaign_audience');
    }
};
