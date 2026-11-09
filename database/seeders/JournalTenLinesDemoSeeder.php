<?php

namespace Database\Seeders;

use App\Models\Accounting\Journal;
use App\Models\Master\BudgetLine;
use App\Models\Master\ChartOfAccount;
use App\Models\Master\Currency;
use App\Models\Master\Department;
use App\Models\Master\Donor;
use App\Models\Master\FiscalYear;
use Illuminate\Database\Seeder;

/** Manual demo fixture: one balanced voucher with ten debit/credit lines. */
class JournalTenLinesDemoSeeder extends Seeder
{
    public function run(): void
    {
        $fiscalYear = FiscalYear::query()->where('is_active', true)->first() ?? FiscalYear::query()->latest('year')->firstOrFail();
        $currency = Currency::query()->where('code', 'IDR')->first();
        $accounts = ChartOfAccount::query()->where('is_header', false)->orderBy('id')->take(10)->get();
        $donor = Donor::query()->orderBy('id')->first();
        $budget = BudgetLine::query()->orderBy('id')->first();
        $department = Department::query()->orderBy('id')->first();

        if ($accounts->count() < 2 || ! $donor || ! $budget) {
            $this->command?->warn('Journal demo dilewati: COA, donor, atau budget line belum tersedia.');
            return;
        }

        $number = 'JV-DEMO-10-LINES-'.$fiscalYear->year;
        $journal = Journal::updateOrCreate(['journal_number' => $number], [
            'fiscal_year_id' => $fiscalYear->id,
            'journal_date' => now()->toDateString(),
            'journal_type' => 'manual',
            'reference' => 'REF-DEMO-10-LINES',
            'description' => 'Dummy journal dengan 10 debit & credit lines',
            'currency_id' => $currency?->id,
            'exchange_rate' => 1,
            'status' => 'draft',
        ]);
        $journal->lines()->delete();

        for ($i = 1; $i <= 10; $i++) {
            $amount = 1000000;
            $journal->lines()->create([
                'account_id' => $accounts[($i - 1) % $accounts->count()]->id,
                'donor_id' => $donor->id,
                'budget_line_id' => $budget->id,
                'department_id' => $department?->id,
                'line_description' => "Dummy journal line {$i}",
                'class' => 'Demo',
                'debit' => $i % 2 === 1 ? $amount : 0,
                'credit' => $i % 2 === 0 ? $amount : 0,
                'line_order' => $i,
            ]);
        }
    }
}
