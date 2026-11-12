<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('projects') && Schema::hasTable('project_fiscal_year') && Schema::hasTable('fiscal_years')) {
            $activeFiscalYearId = DB::table('fiscal_years')
                ->where('is_active', true)
                ->orderByDesc('year')
                ->value('id');
            $allFiscalYears = DB::table('fiscal_years')->select('id', 'start_date', 'end_date')->get();

            $projects = DB::table('projects')
                ->whereNull('deleted_at')
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('project_fiscal_year')
                        ->whereColumn('project_fiscal_year.project_id', 'projects.id');
                })
                ->get(['id', 'grant_agreement_id', 'start_date', 'end_date']);

            foreach ($projects as $project) {
                $grant = $project->grant_agreement_id
                    ? DB::table('grant_agreements')->where('id', $project->grant_agreement_id)->first(['start_date', 'end_date', 'fiscal_year_id'])
                    : null;
                $startDate = $project->start_date ?: $grant?->start_date;
                $endDate = $project->end_date ?: $grant?->end_date;

                $yearIds = collect();
                if ($grant?->fiscal_year_id) {
                    $yearIds->push((int) $grant->fiscal_year_id);
                }
                if ($startDate && $endDate) {
                    foreach ($allFiscalYears as $fy) {
                        if ($fy->start_date && $fy->end_date && $fy->start_date <= $endDate && $fy->end_date >= $startDate) {
                            $yearIds->push((int) $fy->id);
                        }
                    }
                }
                if ($yearIds->isEmpty() && $activeFiscalYearId) {
                    $yearIds->push((int) $activeFiscalYearId);
                }

                foreach ($yearIds->unique() as $yearId) {
                    DB::table('project_fiscal_year')->insertOrIgnore([
                        'project_id' => $project->id,
                        'fiscal_year_id' => $yearId,
                    ]);
                }
            }
        }

        if (Schema::hasTable('menus')) {
            $donorGrantMenu = DB::table('menus')->where('slug', 'donor-grant')->first();
            if ($donorGrantMenu) {
                DB::table('menus')
                    ->whereIn('slug', [
                        'donor-grant-integrated-record',
                        'donor-grant-donors',
                        'donor-grant-grants',
                        'donor-grant-project-workplan',
                        'donor-grant-reporting',
                        'donor-grant-donor-reporting',
                    ])
                    ->update(['is_active' => false, 'updated_at' => now()]);

                $canonicalItems = [
                    ['slug' => 'donor-grant-dashboard', 'title' => 'Dashboard', 'path' => '/donor-grant/dashboard', 'sort_order' => 21],
                    ['slug' => 'donor-grant-project-frameworks-canonical', 'title' => 'Project Frameworks', 'path' => '/project-frameworks', 'sort_order' => 22],
                    ['slug' => 'donor-grant-workplan-activities', 'title' => 'Workplan & Activities', 'path' => '/project-timeline-workplan', 'sort_order' => 23],
                    ['slug' => 'donor-grant-project-budget', 'title' => 'Project Budget', 'path' => '/donor-grant/budget', 'sort_order' => 24],
                    ['slug' => 'donor-grant-forecast-canonical', 'title' => 'Forecast', 'path' => '/donor-grant/forecast-disbursement', 'sort_order' => 25],
                    ['slug' => 'donor-grant-budget-monitoring-canonical', 'title' => 'Budget Monitoring', 'path' => '/donor-grant/budget-monitoring', 'sort_order' => 26],
                    ['slug' => 'donor-grant-donor-reporting-canonical', 'title' => 'Donor Reporting', 'path' => '/donor-grant/donor-reporting', 'sort_order' => 27],
                ];

                $roleIds = Schema::hasTable('roles') ? DB::table('roles')->pluck('id') : collect();
                foreach ($canonicalItems as $item) {
                    $existing = DB::table('menus')->where('slug', $item['slug'])->first();
                    $values = [
                        'parent_id' => $donorGrantMenu->id,
                        'title' => $item['title'],
                        'path' => $item['path'],
                        'sort_order' => $item['sort_order'],
                        'is_active' => true,
                        'updated_at' => now(),
                    ];
                    if ($existing) {
                        DB::table('menus')->where('id', $existing->id)->update($values);
                        $menuId = $existing->id;
                    } else {
                        $menuId = DB::table('menus')->insertGetId([
                            ...$values,
                            'slug' => $item['slug'],
                            'created_at' => now(),
                        ]);
                    }
                    if (Schema::hasTable('menu_role')) {
                        foreach ($roleIds as $roleId) {
                            DB::table('menu_role')->insertOrIgnore([
                                'role_id' => $roleId,
                                'menu_id' => $menuId,
                            ]);
                        }
                    }
                }
            }
        }
    }

    public function down(): void
    {
        // Non-destructive backfill
    }
};

