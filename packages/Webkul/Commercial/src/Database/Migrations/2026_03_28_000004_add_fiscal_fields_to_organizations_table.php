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
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('cnpj', 18)->nullable()->unique()->after('name');
            $table->string('legal_name')->nullable()->after('cnpj');
            $table->string('trade_name')->nullable()->after('legal_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropUnique(['cnpj']);
            $table->dropColumn(['cnpj', 'legal_name', 'trade_name']);
        });
    }
};
