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
        // Keep the default environment limited to login, roles/permissions,
        // menus, and approval workflow foundations. All feature/demo/master
        // data must be requested explicitly through its dedicated seeder.
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            ApprovalMatrixSeeder::class,
        ]);
    }
}
