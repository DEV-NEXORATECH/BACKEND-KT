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

        $oldIds = DB::table('menus')->whereIn('slug', [
            'master-donor-types', 'master-programs', 'master-funding-sources',
        ])->pluck('id');

        if ($oldIds->isNotEmpty() && DB::getSchemaBuilder()->hasTable('menu_role')) {
            DB::table('menu_role')->whereIn('menu_id', $oldIds)->delete();
        }

        DB::table('menus')->whereIn('id', $oldIds)->update([
            'is_active' => false,
            'updated_at' => now(),
        ]);

        $reference = DB::table('menus')->where('slug', 'master-donor-grant-reference')->first();
        $values = [
            'title' => 'Master Referensi',
            'path' => '/master-data/donor-types',
            'parent_id' => $parent->id,
            'sort_order' => 180,
            'is_active' => true,
            'updated_at' => now(),
        ];

        if ($reference) {
            DB::table('menus')->where('id', $reference->id)->update($values);
        } else {
            DB::table('menus')->insert([
                ...$values,
                'slug' => 'master-donor-grant-reference',
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void {}
};
