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
        Schema::table('leads', function (Blueprint $table) {
            $table->unsignedInteger('crm_product_id')->nullable()->after('lead_pipeline_stage_id');

            $table->foreign('crm_product_id')
                ->references('id')
                ->on('crm_products')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['crm_product_id']);
            $table->dropColumn('crm_product_id');
        });
    }
};
