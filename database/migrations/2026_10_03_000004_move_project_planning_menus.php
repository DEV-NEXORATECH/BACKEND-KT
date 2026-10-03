<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('menus')->whereIn('slug', ['master-programs', 'master-projects', 'master-activities', 'donor-grant-project-frameworks'])->update(['is_active' => false]);
    }

    public function down(): void
    {
        DB::table('menus')->whereIn('slug', ['master-programs', 'master-projects', 'master-activities', 'donor-grant-project-frameworks'])->update(['is_active' => true]);
    }
};
