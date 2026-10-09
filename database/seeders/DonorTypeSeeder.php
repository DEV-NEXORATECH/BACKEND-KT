<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DonorTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => 'GOV', 'name' => 'Government'],
            ['code' => 'NGO', 'name' => 'NGO'],
            ['code' => 'FOUNDATION', 'name' => 'Foundation'],
            ['code' => 'MULTILATERAL', 'name' => 'Multilateral'],
            ['code' => 'BILATERAL', 'name' => 'Bilateral'],
            ['code' => 'CORPORATE', 'name' => 'Corporate'],
            ['code' => 'INDIVIDUAL', 'name' => 'Individual'],
        ] as $type) {
            DB::table('donor_types')->updateOrInsert(
                ['code' => $type['code']],
                [...$type, 'is_active' => true, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        foreach (DB::table('donor_types')->get(['id', 'name']) as $type) {
            DB::table('donors')->where('type', $type->name)->update(['donor_type_id' => $type->id]);
        }
    }
}
