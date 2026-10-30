<?php

namespace Database\Seeders;

use App\Models\Master\BudgetLine;
use App\Models\Master\ChartOfAccount;
use App\Models\Master\ExpenseCategory;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Seeds the expense accounts used by Expense App and links budget lines to
 * an expense CoA. Safe to run repeatedly; existing account IDs are preserved.
 */
class ExpenseChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['code' => '5000', 'name' => 'PROGRAM EXPENSES', 'account_type' => 'expense', 'normal_balance' => 'debit', 'level' => 1, 'is_header' => true, 'parent_code' => null],
            ['code' => '5100', 'name' => 'Personnel and Consultant Fees', 'account_type' => 'expense', 'normal_balance' => 'debit', 'level' => 2, 'is_header' => false, 'parent_code' => '5000'],
            ['code' => '5200', 'name' => 'Direct Activity and Workshop Costs', 'account_type' => 'expense', 'normal_balance' => 'debit', 'level' => 2, 'is_header' => false, 'parent_code' => '5000'],
            ['code' => '5300', 'name' => 'Travel and Field Accommodation', 'account_type' => 'expense', 'normal_balance' => 'debit', 'level' => 2, 'is_header' => false, 'parent_code' => '5000'],
            ['code' => '5400', 'name' => 'Office and Administration', 'account_type' => 'expense', 'normal_balance' => 'debit', 'level' => 2, 'is_header' => false, 'parent_code' => '5000'],
            ['code' => '5500', 'name' => 'Depreciation Expense', 'account_type' => 'expense', 'normal_balance' => 'debit', 'level' => 2, 'is_header' => false, 'parent_code' => '5000'],
            ['code' => '11510', 'name' => 'Employee Loan Receivable', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 2, 'is_header' => false, 'parent_code' => '11000'],
            ['code' => '2000', 'name' => 'LIABILITIES', 'account_type' => 'liability', 'normal_balance' => 'credit', 'level' => 1, 'is_header' => true, 'parent_code' => null],
            ['code' => '2100', 'name' => 'ACCOUNTS PAYABLE', 'account_type' => 'liability', 'normal_balance' => 'credit', 'level' => 2, 'is_header' => true, 'parent_code' => '2000'],
            ['code' => '2110', 'name' => 'Accounts Payable - Suppliers', 'account_type' => 'liability', 'normal_balance' => 'credit', 'level' => 3, 'is_header' => false, 'parent_code' => '2100'],
            ['code' => '2120', 'name' => 'Payroll and Tax Payable', 'account_type' => 'liability', 'normal_balance' => 'credit', 'level' => 3, 'is_header' => false, 'parent_code' => '2100'],
        ];

        $created = [];
        foreach ($accounts as $account) {
            $parentId = $account['parent_code'] ? (($created[$account['parent_code']] ?? null)?->id ?? ChartOfAccount::where('code', $account['parent_code'])->value('id')) : null;
            $created[$account['code']] = ChartOfAccount::withTrashed()->updateOrCreate(
                ['code' => $account['code']],
                [
                    'name' => $account['name'],
                    'account_type' => $account['account_type'],
                    'normal_balance' => $account['normal_balance'],
                    'level' => $account['level'],
                    'is_header' => $account['is_header'],
                    'parent_id' => $parentId,
                    'is_active' => true,
                    'deleted_at' => null,
                ],
            );
        }

        $lineMappings = [
            'BL-FORD-1.1' => '5100',
            'BL-FORD-2.1' => '5200',
            'BL-FORD-3.1' => '5300',
            'BL-FORD-2027-1.1' => '5100',
        ];
        foreach ($lineMappings as $lineCode => $coaCode) {
            $coaId = $created[$coaCode]?->id ?? ChartOfAccount::where('code', $coaCode)->value('id');
            if ($coaId) BudgetLine::where('line_code', $lineCode)->update(['gl_account_id' => $coaId, 'cost_type' => $coaCode]);
        }

        $categoryMappings = [
            'BCAT-100' => '5100',
            'BCAT-200' => '5200',
            'BCAT-300' => '5300',
        ];
        foreach ($categoryMappings as $code => $coaCode) {
            $coaId = $created[$coaCode]?->id ?? ChartOfAccount::where('code', $coaCode)->value('id');
            if ($coaId) ExpenseCategory::where('code', $code)->update(['default_gl_account_id' => $coaId, 'cost_type' => $coaCode]);
        }

        // Repair every existing active Budget Line, including fiscal-year
        // demo rows created by FiscalYearDemoSeeder. No selectable budget
        // line may be left without a COA.
        $coaByCategory = [
            'BCAT-100' => '5100',
            'BCAT-200' => '5200',
            'BCAT-300' => '5300',
        ];
        BudgetLine::query()->with('budgetCategory')->whereNull('gl_account_id')->get()->each(function (BudgetLine $line) use ($created, $coaByCategory) {
            $categoryCode = $line->budgetCategory?->code;
            if (! $categoryCode || ! isset($coaByCategory[$categoryCode])) {
                throw new RuntimeException("Budget Line {$line->line_code} belum memiliki mapping COA eksplisit untuk kategori {$categoryCode}. Tambahkan mapping sebelum menjalankan seeder.");
            }
            $coaCode = $coaByCategory[$categoryCode];
            $coaId = $created[$coaCode]?->id ?? ChartOfAccount::where('code', $coaCode)->value('id');
            if (! $coaId) throw new RuntimeException("COA {$coaCode} untuk Budget Line {$line->line_code} tidak ditemukan.");
            $line->update(['gl_account_id' => $coaId, 'cost_type' => $coaCode]);
        });
    }
}
