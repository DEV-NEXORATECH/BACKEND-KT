<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    private const SLUGS = [
        'timesheet-consultant',
        'timesheet-consultant-register',
        'timesheet-consultant-verification',
        'timesheet-consultant-reports',
    ];

    public function up(): void
    {
        $menuIds = DB::table('menus')->whereIn('slug', self::SLUGS)->pluck('id');

        if ($menuIds->isEmpty()) {
            return;
        }

        DB::table('menu_role')->whereIn('menu_id', $menuIds)->delete();
        DB::table('menus')->whereIn('id', $menuIds)->delete();
    }

    public function down(): void
    {
        // The retired Consultant Timesheet menu intentionally stays removed.
    }
};
