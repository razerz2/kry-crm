<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = Carbon::now();

        $attributes = [
            [
                'code' => 'emails',
                'name' => trans('installer::app.seeders.attributes.persons.emails'),
                'type' => 'email',
                'entity_type' => 'organizations',
                'lookup_type' => null,
                'validation' => null,
                'sort_order' => '3',
                'is_required' => '0',
                'is_unique' => '0',
                'quick_add' => '1',
                'is_user_defined' => '0',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'contact_numbers',
                'name' => trans('installer::app.seeders.attributes.persons.contact-numbers'),
                'type' => 'phone',
                'entity_type' => 'organizations',
                'lookup_type' => null,
                'validation' => null,
                'sort_order' => '4',
                'is_required' => '0',
                'is_unique' => '0',
                'quick_add' => '1',
                'is_user_defined' => '0',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($attributes as $attribute) {
            $exists = DB::table('attributes')
                ->where('code', $attribute['code'])
                ->where('entity_type', $attribute['entity_type'])
                ->exists();

            if (! $exists) {
                DB::table('attributes')->insert($attribute);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
