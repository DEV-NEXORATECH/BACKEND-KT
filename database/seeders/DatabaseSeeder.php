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
        // Keep the default environment intentionally empty outside the
        // access-control and approval foundations. Feature/demo data must be
        // requested explicitly through its dedicated seeder.
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            ApprovalMatrixSeeder::class,
        ]);
    }
}
