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
            BudgetAlertThresholdSeeder::class,
            MasterMenuSeeder::class,
            UserSeeder::class,
            // Users must exist before the approval matrix is created so
            // configured budget holders can be linked to their employees.
            ExpenseApprovalChainSeeder::class,
            ApprovalMatrixSeeder::class,
            ProcessDataSeeder::class,
        ]);
    }
}
