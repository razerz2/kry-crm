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
        $hasEmails = Schema::hasColumn('organizations', 'emails');
        $hasContactNumbers = Schema::hasColumn('organizations', 'contact_numbers');

        Schema::table('organizations', function (Blueprint $table) use ($hasEmails, $hasContactNumbers) {
            if (! $hasEmails) {
                $table->json('emails')->nullable()->after('address');
            }

            if (! $hasContactNumbers) {
                $table->json('contact_numbers')->nullable()->after('emails');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $hasEmails = Schema::hasColumn('organizations', 'emails');
        $hasContactNumbers = Schema::hasColumn('organizations', 'contact_numbers');

        Schema::table('organizations', function (Blueprint $table) use ($hasEmails, $hasContactNumbers) {
            $columnsToDrop = [];

            if ($hasContactNumbers) {
                $columnsToDrop[] = 'contact_numbers';
            }

            if ($hasEmails) {
                $columnsToDrop[] = 'emails';
            }

            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
