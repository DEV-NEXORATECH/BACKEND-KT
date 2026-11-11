<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $masterId = DB::table('menus')->where('slug', 'master-data')->value('id');
        if (! $masterId) return;

        $membershipId = DB::table('menus')->where('slug', 'master-membership')->value('id');
        if (! $membershipId) {
            $membershipId = DB::table('menus')->insertGetId([
                'parent_id' => $masterId,
                'title' => 'Membership & Dues',
                'slug' => 'master-membership',
                'path' => '/master-data',
                'sort_order' => 16,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menus')->where('id', $membershipId)->update([
                'parent_id' => $masterId,
                'title' => 'Membership & Dues',
                'path' => '/master-data',
                'sort_order' => 16,
                'is_active' => true,
                'updated_at' => now(),
            ]);
        }

        DB::table('menus')->where('slug', 'master-customers')->update([
            'parent_id' => $membershipId, 'sort_order' => 160, 'is_active' => true, 'updated_at' => now(),
        ]);
        DB::table('menus')->where('slug', 'master-membership-dues')->update([
            'parent_id' => $membershipId, 'sort_order' => 161, 'is_active' => true, 'updated_at' => now(),
        ]);

        // Deactivate stale duplicate rows created by older seeders. The two
        // canonical slugs above are the only membership entries that may show.
        $customerId = DB::table('menus')->where('slug', 'master-customers')->value('id');
        $duesId = DB::table('menus')->where('slug', 'master-membership-dues')->value('id');
        DB::table('menus')->where('path', '/master-data/customers')->when($customerId, fn ($query) => $query->where('id', '!=', $customerId))->update(['is_active' => false, 'updated_at' => now()]);
        DB::table('menus')->where('path', '/master-data/membership-dues')->when($duesId, fn ($query) => $query->where('id', '!=', $duesId))->update(['is_active' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Keep the corrected hierarchy on rollback; older migrations already
        // contain their own reversible menu transitions.
    }
};
