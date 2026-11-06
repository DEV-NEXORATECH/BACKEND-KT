<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('menus')->whereIn('slug', [
            'accounts-payable-workflow',
            'accounts-receivable-workflow',
        ])->delete();
    }

    public function down(): void
    {
        // Removed navigation items are intentionally not restored.
    }
};
