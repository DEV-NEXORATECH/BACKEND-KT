<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $masterId = DB::table('menus')->where('slug', 'master-data')->value('id');
        if (! $masterId) {
            return;
        }

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
        }

        DB::table('menus')->where('id', $membershipId)->update([
            'parent_id' => $masterId,
            'title' => 'Membership & Dues',
            'path' => '/master-data',
            'sort_order' => 16,
            'is_active' => true,
            'updated_at' => now(),
        ]);

        DB::table('menus')->where('slug', 'master-customers')->update([
            'parent_id' => $membershipId,
            'title' => 'Members / Anggota',
            'sort_order' => 160,
            'is_active' => true,
            'updated_at' => now(),
        ]);
        DB::table('menus')->where('slug', 'master-membership-dues')->update([
            'parent_id' => $membershipId,
            'title' => 'Iuran Anggota',
            'sort_order' => 161,
            'is_active' => true,
            'updated_at' => now(),
        ]);

        $customerId = DB::table('menus')->where('slug', 'master-customers')->value('id');
        $duesId = DB::table('menus')->where('slug', 'master-membership-dues')->value('id');
        if ($customerId) {
            DB::table('menus')->where('path', '/master-data/customers')->where('id', '!=', $customerId)->update(['is_active' => false, 'updated_at' => now()]);
        }
        if ($duesId) {
            DB::table('menus')->where('path', '/master-data/membership-dues')->where('id', '!=', $duesId)->update(['is_active' => false, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Do not restore the incorrect Finance & Accounting hierarchy.
    }
};
