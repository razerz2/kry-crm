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
        Schema::create('account_product_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('account_product_id');
            $table->unsignedInteger('lead_id')->nullable();
            $table->string('old_status')->nullable();
            $table->string('new_status');
            $table->unsignedInteger('changed_by')->nullable();
            $table->string('source')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('account_product_id')
                ->references('id')
                ->on('account_products')
                ->onDelete('cascade');

            $table->foreign('lead_id')
                ->references('id')
                ->on('leads')
                ->onDelete('set null');

            $table->foreign('changed_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index(['account_product_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_product_histories');
    }
};
