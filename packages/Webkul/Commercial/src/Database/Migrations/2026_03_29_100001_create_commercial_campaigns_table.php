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
        Schema::create('commercial_campaigns', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('channel')->default('email');
            $table->string('status')->default('draft');
            $table->string('subject')->nullable();
            $table->longText('message_body')->nullable();
            $table->json('filters_json')->nullable();
            $table->datetime('audience_generated_at')->nullable();
            $table->unsignedInteger('total_audience')->default(0);
            $table->unsignedInteger('total_with_email')->default(0);
            $table->unsignedInteger('total_with_phone')->default(0);

            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->unsignedInteger('updated_by')->nullable();
            $table->foreign('updated_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->timestamps();

            $table->index('status');
            $table->index('channel');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commercial_campaigns');
    }
};
