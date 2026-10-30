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
            CompleteFeatureDataSeeder::class,
            // Feature seeders create the operational Budget Lines. Repair
            // their explicit COA mappings before fiscal-year clones validate
            // those lines.
            ExpenseChartOfAccountsSeeder::class,
            // Apply fiscal-year backfill and create the 2025/2027 counterparts
            // after every feature seeder has finished creating its records.
            FiscalYearDemoSeeder::class,
        ]);
    }
}
