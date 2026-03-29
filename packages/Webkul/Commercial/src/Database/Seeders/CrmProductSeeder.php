<?php

namespace Webkul\Commercial\Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CrmProductSeeder extends Seeder
{
    /**
     * Seed the CRM products table.
     *
     * This seeder is idempotent: it uses upsert to avoid duplicates on re-runs.
     */
    public function run(): void
    {
        $now = Carbon::now();

        DB::table('crm_products')->upsert(
            [
                [
                    'name' => 'AllSync',
                    'slug' => 'allsync',
                    'description' => 'Produto principal de sincronização AllSync.',
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ],
            ['slug'],
            ['name', 'description', 'is_active', 'updated_at']
        );
    }
}
