<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commercial_campaigns', function (Blueprint $table) {
            $table->string('execution_type')->default('manual')->after('status');
            $table->string('timezone')->default(config('app.timezone', 'UTC'))->after('execution_type');

            $table->dateTime('run_at')->nullable()->after('timezone');
            $table->dateTime('starts_at')->nullable()->after('run_at');
            $table->dateTime('ends_at')->nullable()->after('starts_at');

            $table->string('recurrence_type')->nullable()->after('ends_at');
            $table->unsignedInteger('interval_value')->nullable()->after('recurrence_type');
            $table->string('interval_unit')->nullable()->after('interval_value');
            $table->json('days_of_week')->nullable()->after('interval_unit');
            $table->unsignedTinyInteger('day_of_month')->nullable()->after('days_of_week');
            $table->time('time_of_day')->nullable()->after('day_of_month');

            $table->time('window_start_time')->nullable()->after('time_of_day');
            $table->time('window_end_time')->nullable()->after('window_start_time');

            $table->unsignedInteger('max_runs')->nullable()->after('window_end_time');
            $table->dateTime('last_run_at')->nullable()->after('max_runs');
            $table->dateTime('next_run_at')->nullable()->after('last_run_at');

            $table->index(['status', 'next_run_at'], 'cc_status_next_run_idx');
            $table->index('execution_type', 'cc_execution_type_idx');
        });
    }

    public function down(): void
    {
        Schema::table('commercial_campaigns', function (Blueprint $table) {
            $table->dropIndex('cc_status_next_run_idx');
            $table->dropIndex('cc_execution_type_idx');

            $table->dropColumn([
                'execution_type',
                'timezone',
                'run_at',
                'starts_at',
                'ends_at',
                'recurrence_type',
                'interval_value',
                'interval_unit',
                'days_of_week',
                'day_of_month',
                'time_of_day',
                'window_start_time',
                'window_end_time',
                'max_runs',
                'last_run_at',
                'next_run_at',
            ]);
        });
    }
};
