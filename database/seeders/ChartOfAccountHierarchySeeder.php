<?php

namespace Database\Seeders;

use App\Models\Master\ChartOfAccount;
use Illuminate\Database\Seeder;

class ChartOfAccountHierarchySeeder extends Seeder
{
    /** Seed a visible two-layer Header Account -> Child Account example. */
    public function run(): void
    {
        $parent = ChartOfAccount::withoutGlobalScopes()->withTrashed()->updateOrCreate(
            ['code' => '9000'],
            [
                'name' => 'Header Account - Program Costs',
                'account_type' => 'expense',
                'normal_balance' => 'debit',
                'level' => 1,
                'is_header' => true,
                'parent_id' => null,
                'is_active' => true,
                'deleted_at' => null,
            ]
        );

        foreach ([
            ['code' => '9001', 'name' => 'Child Account 1 - Field Activities'],
            ['code' => '9002', 'name' => 'Child Account 2 - Workshop Activities'],
        ] as $child) {
            ChartOfAccount::withoutGlobalScopes()->withTrashed()->updateOrCreate(
                ['code' => $child['code']],
                [
                    'name' => $child['name'],
                    'account_type' => 'expense',
                    'normal_balance' => 'debit',
                    'level' => 2,
                    'is_header' => false,
                    'parent_id' => $parent->id,
                    'is_active' => true,
                    'deleted_at' => null,
                ]
            );
        }
    }
}
