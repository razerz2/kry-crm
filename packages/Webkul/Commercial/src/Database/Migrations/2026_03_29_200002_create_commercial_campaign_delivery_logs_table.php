<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_campaign_delivery_logs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('commercial_campaign_delivery_id');
            $table->foreign('commercial_campaign_delivery_id', 'ccdl_delivery_fk')
                ->references('id')
                ->on('commercial_campaign_deliveries')
                ->onDelete('cascade');

            // info | warning | error | debug
            $table->string('level')->nullable()->default('info');
            $table->text('message');
            $table->json('context_json')->nullable();

            $table->timestamps();

            $table->index('commercial_campaign_delivery_id', 'ccdl_delivery_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_campaign_delivery_logs');
    }
};
