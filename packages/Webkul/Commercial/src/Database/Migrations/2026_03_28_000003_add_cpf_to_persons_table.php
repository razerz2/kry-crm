<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * CPF is stored as a 14-char nullable string (with mask: 000.000.000-00).
     * We use a unique index but allow NULL — MySQL/MariaDB treats each NULL as distinct,
     * so multiple rows with NULL cpf are allowed, and duplicate CPFs are blocked.
     * This is standard SQL behaviour and works correctly on MySQL 5.7+, MariaDB, PostgreSQL.
     */
    public function up(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            $table->string('cpf', 14)->nullable()->unique()->after('job_title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            $table->dropUnique(['cpf']);
            $table->dropColumn('cpf');
        });
    }
};
