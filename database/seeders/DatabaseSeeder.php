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
            ExpenseChartOfAccountsSeeder::class,
            DonorTypeSeeder::class,
            MasterMenuSeeder::class,
            UserSeeder::class,
            ApprovalMatrixSeeder::class,
            ProcessDataSeeder::class,
            // Process data creates the projects. Seed the expense chain last
            // so every project receives its own three-level matrix.
            ExpenseApprovalChainSeeder::class,
            // Keep budget monitoring free of obsolete synthetic alert rows
            // after all real budget lines have been created.
            BudgetAlertThresholdSeeder::class,
        ]);
    }
}
