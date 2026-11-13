<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Journal;
use App\Models\Accounting\JournalLine;
use App\Models\Finance\BankTransaction;
use App\Models\Finance\CustomerInvoice;
use App\Models\Master\BankAccount;
use App\Models\Master\BudgetLine;
use App\Models\Master\FiscalYear;
use App\Models\Procurement\SupplierInvoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Carbon;

class AccountingDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $selectedFiscalYear = $request->filled('fiscal_year_id')
            ? FiscalYear::query()->find($request->integer('fiscal_year_id'))
            : null;
        $fiscalStart = $selectedFiscalYear?->start_date ? Carbon::parse($selectedFiscalYear->start_date) : now()->startOfYear();
        $fiscalEnd = $selectedFiscalYear?->end_date ? Carbon::parse($selectedFiscalYear->end_date) : now();
        $today = $fiscalEnd->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $yearStart = $fiscalStart->toDateString();
        $start = $request->query('start_date', $monthStart);
        $end = $request->query('end_date', $today);
        if ($selectedFiscalYear) {
            $start = $request->query('start_date', $fiscalStart->toDateString());
            $end = $request->query('end_date', $fiscalEnd->toDateString());
        }
        $bankAccountId = $request->integer('bank_account_id') ?: null;
        $projectId = $request->integer('project_id') ?: null;

        if ($bankAccountId && $projectId) {
            $projectBankAccountId = \App\Models\Master\Project::query()->whereKey($projectId)->value('bank_account_id');
            if (! $projectBankAccountId || (int) $projectBankAccountId !== $bankAccountId) {
                throw ValidationException::withMessages(['bank_account_id' => 'Bank account harus terhubung dengan project yang dipilih.']);
            }
        }

        $postedLines = fn () => JournalLine::query()->with([
            'account.category',
            'project:id,code,name',
            'donor:id,code,name',
            'program:id,code,name',
            'budgetLine.project:id,code,name',
            'grantAgreement.project:id,code,name',
            'grantAgreement.donor:id,code,name',
        ])
        ->whereHas('journal', fn (Builder $query) => $query->where('status', 'posted')->whereBetween('journal_date', [$start, $end]));

        $periodLines = $postedLines()->get();
        $ytdLines = JournalLine::query()->with('account')->whereHas('journal', fn (Builder $query) => $query->where('status', 'posted')->whereBetween('journal_date', [$yearStart, $today]))->get();

        $income = fn ($lines) => round((float) $lines->filter(fn ($line) => in_array(strtolower((string) $line->account?->account_type), ['income', 'revenue'], true))->sum(fn ($line) => (float) $line->credit - (float) $line->debit), 2);
        $expenses = fn ($lines) => round((float) $lines->filter(fn ($line) => strtolower((string) $line->account?->account_type) === 'expense')->sum(fn ($line) => (float) $line->debit - (float) $line->credit), 2);
        $expenseLines = $periodLines->filter(fn ($line) => strtolower((string) $line->account?->account_type) === 'expense');

        $resolveProjectName = function ($line) {
            return $line->project?->name
                ?? $line->budgetLine?->project?->name
                ?? $line->grantAgreement?->project?->name
                ?? ($line->program?->name ? 'Program: ' . $line->program->name : 'Operasional / Non-Project');
        };

        $resolveDonorName = function ($line) {
            return $line->donor?->name
                ?? $line->grantAgreement?->donor?->name
                ?? $line->budgetLine?->grantAgreement?->donor?->name
                ?? 'Dana Operasional / Internal';
        };

        $resolveAccountName = function ($line) {
            return $line->account?->name ?? 'Biaya Operasional Lainnya';
        };

        $resolveCategoryName = function ($line) {
            return $line->account?->category?->name ?? 'Biaya Operasional Umum';
        };

        $bankAccounts = BankAccount::query()->with(['currency:id,code,name'])->where('is_active', true)
            ->when($bankAccountId, fn ($query) => $query->whereKey($bankAccountId))
            ->when($projectId, fn ($query) => $query->whereHas('projects', fn ($project) => $project->whereKey($projectId)))
            ->get();
        $cash = $bankAccounts->map(function (BankAccount $account) {
            $transactions = BankTransaction::query()->where('bank_account_id', $account->id)->whereHas('bankAccount')->where('status', '!=', 'excluded')->get();
            return ['id' => $account->id, 'bank_name' => $account->bank_name, 'account_name' => $account->account_name, 'account_number' => $account->account_number, 'currency' => $account->currency?->code ?? 'IDR', 'opening_balance' => (float) $account->opening_balance, 'balance' => round((float) $account->opening_balance + (float) $transactions->sum('debit') - (float) $transactions->sum('credit'), 2), 'incoming' => round((float) $transactions->sum('debit'), 2), 'outgoing' => round((float) $transactions->sum('credit'), 2)];
        })->values();

        $ap = SupplierInvoice::query()->with('vendor:id,name')->whereNotIn('status', ['paid', 'cancelled'])->latest('due_date')->get()->map(fn ($invoice) => ['id' => $invoice->id, 'invoice_number' => $invoice->invoice_number, 'vendor' => $invoice->vendor?->name, 'due_date' => $invoice->due_date?->toDateString(), 'status' => $invoice->status, 'outstanding_amount' => round((float) $invoice->total_amount - (float) $invoice->paid_amount, 2)])->values();
        $ar = CustomerInvoice::query()->with('customer:id,name')->whereNotIn('status', ['received', 'cancelled'])->latest('due_date')->get()->map(fn ($invoice) => ['id' => $invoice->id, 'invoice_number' => $invoice->invoice_number, 'customer' => $invoice->customer?->name, 'due_date' => $invoice->due_date?->toDateString(), 'status' => $invoice->status, 'outstanding_amount' => round((float) $invoice->total_amount - (float) $invoice->received_amount, 2)])->values();
        $unreconciled = BankTransaction::query()->with('bankAccount:id,bank_name,account_number')->whereHas('bankAccount')->whereNotIn('status', ['reconciled', 'excluded'])->latest('transaction_date')->limit(10)->get()->map(fn ($item) => ['id' => $item->id, 'date' => $item->transaction_date?->toDateString(), 'reference' => $item->reference, 'description' => $item->description, 'amount' => round((float) $item->debit + (float) $item->credit, 2), 'status' => $item->status, 'bank_account' => $item->bankAccount?->account_number])->values();
        $pending = Journal::query()->whereIn('status', ['submitted', 'reviewed'])->latest('journal_date')->limit(10)->get()->map(fn ($journal) => ['id' => $journal->id, 'journal_number' => $journal->journal_number, 'date' => $journal->journal_date?->toDateString(), 'status' => $journal->status, 'description' => $journal->description, 'total' => (float) $journal->lines()->sum('debit')])->values();

        $recent = Journal::query()->with([
            'lines.project:id,code,name',
            'lines.donor:id,code,name',
            'lines.program:id,code,name',
            'lines.budgetLine.project:id,code,name',
            'lines.grantAgreement.project:id,code,name',
            'lines.grantAgreement.donor:id,code,name',
        ])
        ->where('status', 'posted')
        ->latest('journal_date')
        ->latest('id')
        ->limit(10)
        ->get()
        ->map(function ($journal) {
            $line = $journal->lines->first(fn ($l) => $l->project_id || $l->donor_id || $l->program_id || $l->budget_line_id || $l->grant_agreement_id) ?? $journal->lines->first();
            $fundGrant = $line ? ($line->project?->name
                ?? $line->budgetLine?->project?->name
                ?? $line->grantAgreement?->project?->name
                ?? $line->donor?->name
                ?? $line->grantAgreement?->donor?->name
                ?? ($line->program?->name ? 'Program: ' . $line->program->name : 'Operasional / Non-Project'))
                : 'Operasional / Non-Project';
            return [
                'id' => $journal->id,
                'journal_number' => $journal->journal_number,
                'date' => $journal->journal_date?->toDateString(),
                'reference' => $journal->reference,
                'source_type' => $journal->source_type,
                'source_id' => $journal->source_id,
                'description' => $journal->description,
                'fund_grant' => $fundGrant,
                'status' => $journal->status,
                'amount' => round((float) $journal->lines->sum('debit'), 2),
                'source_url' => '/accounting/journal/'.$journal->id,
            ];
        })->values();

        $budget = BudgetLine::query()
            ->with(['project:id,code,name', 'grantAgreement.donor:id,code,name', 'grantAgreement.project:id,code,name'])
            ->get()
            ->groupBy(fn ($line) => $line->project_id ?: ($line->grantAgreement?->project_id ?: 'operational'))
            ->map(function ($rows) use ($periodLines) {
                $project = $rows->first()->project ?? $rows->first()->grantAgreement?->project;
                $projectId = $rows->first()->project_id ?: $rows->first()->grantAgreement?->project_id;
                $actual = $projectId ? $periodLines->where('project_id', $projectId)->sum(fn ($line) => (float) $line->debit - (float) $line->credit) : 0;
                return [
                    'project' => $project ? [
                        'id' => $project->id,
                        'code' => $project->code,
                        'name' => $project->name,
                    ] : [
                        'id' => null,
                        'code' => 'OPR',
                        'name' => 'Operasional / Non-Project',
                    ],
                    'project_name' => $project?->name ?? 'Operasional / Non-Project',
                    'project_code' => $project?->code ?? 'OPR',
                    'budget' => round((float) $rows->sum('base_amount'), 2),
                    'actual' => round((float) $actual, 2),
                    'available' => round((float) $rows->sum('base_amount') - (float) $actual, 2),
                ];
            })->values();

        return response()->json([
            'success' => true,
            'period' => ['start_date' => $start, 'end_date' => $end, 'ytd_start_date' => $yearStart],
            'cash_and_bank' => $cash,
            'income' => [
                'current_period' => $income($periodLines),
                'ytd' => $income($ytdLines),
            ],
            'expenses' => [
                'current_period' => $expenses($periodLines),
                'by_account' => $expenseLines->groupBy($resolveAccountName)->map(fn ($rows, $label) => ['label' => $label, 'amount' => round((float) $rows->sum(fn ($line) => (float) $line->debit - (float) $line->credit), 2)])->values(),
                'by_project' => $expenseLines->groupBy($resolveProjectName)->map(fn ($rows, $label) => ['label' => $label, 'amount' => round((float) $rows->sum(fn ($line) => (float) $line->debit - (float) $line->credit), 2)])->values(),
                'by_donor' => $expenseLines->groupBy($resolveDonorName)->map(fn ($rows, $label) => ['label' => $label, 'amount' => round((float) $rows->sum(fn ($line) => (float) $line->debit - (float) $line->credit), 2)])->values(),
                'by_category' => $expenseLines->groupBy($resolveCategoryName)->map(fn ($rows, $label) => ['label' => $label, 'amount' => round((float) $rows->sum(fn ($line) => (float) $line->debit - (float) $line->credit), 2)])->values(),
            ],
            'accounts_payable' => ['total_outstanding' => round((float) $ap->sum('outstanding_amount'), 2), 'items' => $ap],
            'accounts_receivable' => ['total_outstanding' => round((float) $ar->sum('outstanding_amount'), 2), 'items' => $ar],
            'unreconciled_bank_transactions' => ['count' => BankTransaction::query()->whereNotIn('status', ['reconciled', 'excluded'])->count(), 'items' => $unreconciled],
            'pending_journals' => ['count' => Journal::query()->whereIn('status', ['submitted', 'reviewed'])->count(), 'items' => $pending],
            'budget_vs_actual' => $budget,
            'recent_transactions' => $recent,
        ]);
    }
}
