<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $order = [
            'master-donor-types' => 180,
            'master-programs' => 181,
            'master-donor-grant-integrated' => 182,
            'master-funding-sources' => 183,
        ];
        foreach ($order as $slug => $sortOrder) {
            DB::table('menus')->where('slug', $slug)->update(['sort_order' => $sortOrder, 'updated_at' => now()]);
        }
    }

    public function down(): void {}
};
