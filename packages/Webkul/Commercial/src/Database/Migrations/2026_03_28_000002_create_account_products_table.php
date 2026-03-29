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
        Schema::create('account_products', function (Blueprint $table) {
            $table->increments('id');

            $table->string('entity_type');
            $table->unsignedInteger('entity_id');

            $table->unsignedInteger('crm_product_id');
            $table->foreign('crm_product_id')
                ->references('id')
                ->on('crm_products')
                ->onDelete('cascade');

            $table->string('status');

            $table->datetime('started_at')->nullable();
            $table->datetime('ended_at')->nullable();
            $table->text('lost_reason')->nullable();
            $table->text('notes')->nullable();

            $table->unsignedInteger('user_id')->nullable();
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->timestamps();

            $table->index(['entity_type', 'entity_id'], 'account_products_entity_index');

            $table->unique(
                ['entity_type', 'entity_id', 'crm_product_id'],
                'account_products_entity_product_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_products');
    }
};
