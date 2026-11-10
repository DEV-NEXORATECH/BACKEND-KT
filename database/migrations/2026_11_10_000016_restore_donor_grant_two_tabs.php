<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $parent = DB::table('menus')->where('slug', 'master-funding-projects')->first();
        if (! $parent) {
            return;
        }

        DB::table('menus')->whereIn('slug', [
            'master-donor-types', 'master-programs', 'master-funding-sources',
        ])->update(['is_active' => false, 'updated_at' => now()]);

        $tabs = [
            'master-beneficiary-partners' => [
                'title' => 'Implementing Partners',
                'path' => '/master-data/beneficiary-partners',
                'sort_order' => 183,
            ],
            'master-donor-grant-integrated' => [
                'title' => 'Donor & Grant Terintegrasi',
                'path' => '/master-data/donor-grant-integrated',
                'sort_order' => 184,
            ],
        ];

        foreach ($tabs as $slug => $tab) {
            $menu = DB::table('menus')->where('slug', $slug)->first();
            if (! $menu) {
                continue;
            }

            DB::table('menus')->where('id', $menu->id)->update([
                ...$tab,
                'parent_id' => $parent->id,
                'is_active' => true,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void {}
};
