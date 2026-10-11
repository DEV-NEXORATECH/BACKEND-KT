<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            DepartmentSeeder::class,
            MasterDataSeeder::class,
            DonorTypeSeeder::class,
            BudgetAlertThresholdSeeder::class,
            MasterMenuSeeder::class,
            UserSeeder::class,
            ProcessDataSeeder::class,
        ]);
    }
}
