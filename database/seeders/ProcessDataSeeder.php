<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Single entry point for a complete, connected demo process dataset.
 *
 * MasterDataSeeder remains responsible for reference/master records. This
 * seeder fills the operational chain after the master records exist.
 */
class ProcessDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            FeatureDataSeeder::class,
            UiDemoDataSeeder::class,
            FiscalYearDemoSeeder::class,
            CompleteFeatureDataSeeder::class,
        ]);
    }
}
