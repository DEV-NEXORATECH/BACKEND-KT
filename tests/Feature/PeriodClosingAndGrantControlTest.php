<?php

namespace Tests\Feature;

use App\Models\Master\AccountingPeriod;
use App\Models\Master\FiscalYear;
use App\Services\Accounting\AccountingPeriodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PeriodClosingAndGrantControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_closed_period_rejects_backdated_transaction(): void
    {
        $year = FiscalYear::create([
            'year' => 2026, 'name' => 'FY 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
            'status' => 'open', 'is_active' => true,
        ]);
        AccountingPeriod::create([
            'fiscal_year_id' => $year->id, 'period_number' => 9, 'name' => 'September 2026',
            'start_date' => '2026-09-01', 'end_date' => '2026-09-30', 'status' => 'closed', 'is_active' => false,
        ]);

        $this->expectException(ValidationException::class);
        app(AccountingPeriodService::class)->ensureOpen('2026-09-15', 'journal_date');
    }

    public function test_open_period_allows_current_transaction_date(): void
    {
        $year = FiscalYear::create([
            'year' => 2026, 'name' => 'FY 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
            'status' => 'open', 'is_active' => true,
        ]);
        AccountingPeriod::create([
            'fiscal_year_id' => $year->id, 'period_number' => 10, 'name' => 'October 2026',
            'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'status' => 'open', 'is_active' => true,
        ]);

        app(AccountingPeriodService::class)->ensureOpen('2026-10-15', 'journal_date');
        $this->assertTrue(true);
    }
}
