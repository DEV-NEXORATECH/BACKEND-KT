<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Role;
use Illuminate\Database\Seeder;

class MasterMenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $masterMenu = Menu::updateOrCreate(
            ['slug' => 'master-data'],
            [
                'title' => 'Master Data',
                'path' => '/master-data',
                'icon' => 'card-bank',
                'sort_order' => 20,
                'is_active' => true,
            ]
        );

        $submenus = [
            ['title' => 'Chart of Accounts (COA)', 'slug' => 'coa', 'path' => '/master-data/coa'],
            ['title' => 'Fiscal Year & Period', 'slug' => 'fiscal', 'path' => '/master-data/fiscal'],
            ['title' => 'Currency', 'slug' => 'currency', 'path' => '/master-data/currency'],
            ['title' => 'Tax', 'slug' => 'tax', 'path' => '/master-data/tax'],
        ];

        foreach ($submenus as $index => $submenu) {
            Menu::updateOrCreate(
                ['slug' => $submenu['slug']],
                [
                    'parent_id' => $masterMenu->id,
                    'title' => $submenu['title'],
                    'path' => $submenu['path'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }

        $roles = Role::all();
        $menuIds = Menu::pluck('id')->toArray();
        foreach ($roles as $role) {
            $role->menus()->syncWithoutDetaching($menuIds);
        }
    }
}
