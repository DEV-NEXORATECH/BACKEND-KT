<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $timesheet = DB::table('menus')->where('slug', 'timesheet')->first();
        if (! $timesheet) {
            return;
        }

        $findOrCreate = function (array $menu, ?int $parentId) {
            $existing = DB::table('menus')->where('slug', $menu['slug'])->first();
            $values = [
                'parent_id' => $parentId,
                'title' => $menu['title'],
                'path' => $menu['path'],
                'icon' => $menu['icon'] ?? null,
                'sort_order' => $menu['sort_order'],
                'is_active' => true,
                'updated_at' => now(),
            ];
            if ($existing) {
                DB::table('menus')->where('id', $existing->id)->update($values);
                return (int) $existing->id;
            }
            return (int) DB::table('menus')->insertGetId($values + ['slug' => $menu['slug'], 'created_at' => now()]);
        };

        $internal = $findOrCreate(['slug' => 'timesheet-internal', 'title' => 'Internal Timesheet', 'path' => '/timesheet/internal', 'sort_order' => 522], $timesheet->id);
        foreach ([
            ['slug' => 'timesheet-my', 'title' => 'My Timesheet', 'path' => '/timesheet/my-timesheet', 'sort_order' => 5221],
            ['slug' => 'timesheet-team', 'title' => 'Team Timesheet', 'path' => '/timesheet/team-timesheet', 'sort_order' => 5222],
            ['slug' => 'timesheet-approval', 'title' => 'Approval', 'path' => '/timesheet/approval', 'sort_order' => 5223],
        ] as $menu) $findOrCreate($menu, $internal);

        foreach ([
            ['slug' => 'timesheet-external', 'title' => 'External Timesheet', 'path' => '/timesheet/external', 'sort_order' => 523, 'children' => [
                ['slug' => 'timesheet-external-register', 'title' => 'External Timesheet', 'path' => '/timesheet/external/register', 'sort_order' => 5231],
                ['slug' => 'timesheet-external-verification', 'title' => 'Verification', 'path' => '/timesheet/external/verification', 'sort_order' => 5232],
                ['slug' => 'timesheet-external-reports', 'title' => 'Reports', 'path' => '/timesheet/external/reports', 'sort_order' => 5233],
            ]],
            ['slug' => 'timesheet-consultant', 'title' => 'Consultant Timesheet', 'path' => '/timesheet/consultant', 'sort_order' => 524, 'children' => [
                ['slug' => 'timesheet-consultant-register', 'title' => 'Consultant Timesheet', 'path' => '/timesheet/consultant/register', 'sort_order' => 5241],
                ['slug' => 'timesheet-consultant-verification', 'title' => 'Verification', 'path' => '/timesheet/consultant/verification', 'sort_order' => 5242],
                ['slug' => 'timesheet-consultant-reports', 'title' => 'Reports', 'path' => '/timesheet/consultant/reports', 'sort_order' => 5243],
            ]],
        ] as $group) {
            $parent = $findOrCreate($group, $timesheet->id);
            foreach ($group['children'] as $child) $findOrCreate($child, $parent);
        }

        $newMenuIds = DB::table('menus')->whereIn('slug', [
            'timesheet', 'timesheet-dashboard', 'timesheet-internal', 'timesheet-external', 'timesheet-consultant',
            'timesheet-my', 'timesheet-team', 'timesheet-approval', 'timesheet-external-register', 'timesheet-external-verification',
            'timesheet-external-reports', 'timesheet-consultant-register', 'timesheet-consultant-verification', 'timesheet-consultant-reports',
            'timesheet-project', 'timesheet-reports',
        ])->pluck('id');
        $roleIds = DB::table('roles')->pluck('id');
        foreach ($roleIds as $roleId) foreach ($newMenuIds as $menuId) {
            DB::table('menu_role')->insertOrIgnore(['role_id' => $roleId, 'menu_id' => $menuId]);
        }
    }

    public function down(): void
    {
        // Keep menu assignments intact when rolling back.
    }
};
