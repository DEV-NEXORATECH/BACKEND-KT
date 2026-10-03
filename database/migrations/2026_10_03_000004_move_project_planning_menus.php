<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Retire the old master-data and duplicate planning entries, while
        // keeping the canonical planning menus available to every role that
        // already had project access.
        DB::table('menus')->whereIn('slug', ['master-programs', 'master-projects', 'master-activities', 'donor-grant-programs-projects'])->update(['is_active' => false]);
        DB::table('menus')->where('slug', 'donor-grant-project-frameworks')->update([
            'is_active' => true,
            'path' => '/project-frameworks',
            'title' => 'Project Frameworks',
        ]);
        DB::table('menus')->where('slug', 'donor-grant-project-workplan')->update([
            'is_active' => true,
            'path' => '/project-timeline-workplan',
            'title' => 'Project Timeline / Workplan',
        ]);
    }

    public function down(): void
    {
        DB::table('menus')->whereIn('slug', ['master-programs', 'master-projects', 'master-activities', 'donor-grant-programs-projects'])->update(['is_active' => true]);
        DB::table('menus')->where('slug', 'donor-grant-project-frameworks')->update([
            'is_active' => true,
            'path' => '/donor-grant/project-frameworks',
            'title' => 'Project Frameworks',
        ]);
        DB::table('menus')->where('slug', 'donor-grant-project-workplan')->update([
            'is_active' => true,
            'path' => '/donor-grant/project-workplan',
            'title' => 'Project Timeline / Workplan',
        ]);
    }
};
