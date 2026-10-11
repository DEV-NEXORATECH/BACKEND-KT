<?php

namespace Database\Seeders;

use App\Models\Master\AccountingPeriod;
use App\Models\Master\FiscalYear;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Keeps demo data visibly separated by fiscal year so the year filter can be
 * verified without relying on production records.
 */
class FiscalYearDemoSeeder extends Seeder
{
    public function run(): void
    {
        $years = [];
        foreach ([2025, 2026, 2027] as $year) {
            $years[$year] = FiscalYear::updateOrCreate(['year' => $year], [
                'name' => "Tahun Fiskal {$year}",
                'start_date' => "{$year}-01-01",
                'end_date' => "{$year}-12-31",
                'status' => 'open',
                'is_active' => $year === 2026,
            ]);

            for ($period = 1; $period <= 12; $period++) {
                $start = sprintf('%d-%02d-01', $year, $period);
                $end = date('Y-m-t', strtotime($start));
                AccountingPeriod::updateOrCreate(
                    ['fiscal_year_id' => $years[$year]->id, 'period_number' => $period],
                    [
                        'name' => 'Periode '.date('F', strtotime($start))." {$year}",
                        'start_date' => $start,
                        'end_date' => $end,
                        'status' => 'open',
                        'is_active' => true,
                    ],
                );
            }
        }

        // Keep one deterministic record in each year for the most visible
        // transaction screens. Existing 2026 fixtures remain unchanged.
        $fy2026 = $years[2026]->id;
        $fy2027 = $years[2027]->id;

        DB::table('expense_requests')
            ->where('request_number', 'EXP-2026-004')
            ->update(['fiscal_year_id' => $fy2027, 'request_date' => '2027-03-14']);

        DB::table('timesheet_entries')
            ->where('description', 'Financial monitoring and reporting')
            ->update(['fiscal_year_id' => $fy2027, 'entry_date' => '2027-03-05']);

        $sourcePr = DB::table('purchase_requests')->where('pr_number', 'PR-2026-001')->first();
        if ($sourcePr) {
            $pr = (array) $sourcePr;
            unset($pr['id']);
            $pr['pr_number'] = 'PR-2027-001';
            $pr['fiscal_year_id'] = $fy2027;
            $pr['request_date'] = '2027-02-05';
            $pr['created_at'] = now();
            $pr['updated_at'] = now();
            DB::table('purchase_requests')->updateOrInsert(
                ['pr_number' => 'PR-2027-001'],
                $pr,
            );
        }

        // Make the intended default explicit after older seeders have run.
        DB::table('fiscal_years')->where('id', '!=', $fy2026)->update(['is_active' => false]);
        DB::table('fiscal_years')->where('id', $fy2026)->update(['is_active' => true]);
    }
}
