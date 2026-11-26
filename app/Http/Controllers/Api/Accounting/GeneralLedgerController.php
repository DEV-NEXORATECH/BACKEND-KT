<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\JournalLine;
use Illuminate\Http\Request;

class GeneralLedgerController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['fiscal_year_id' => 'nullable|exists:fiscal_years,id', 'start_date' => 'nullable|date', 'end_date' => 'nullable|date', 'account_id' => 'nullable|exists:chart_of_accounts,id', 'project_id' => 'nullable|exists:projects,id', 'donor_id' => 'nullable|exists:donors,id', 'department_id' => 'nullable|exists:departments,id']);
        $query = JournalLine::with(['journal:id,journal_number,journal_date,reference,description,status', 'account:id,code,name'])
            ->whereHas('journal', fn ($journal) => $journal->where('status', 'posted'))
            ->when($filters['fiscal_year_id'] ?? null, fn ($query, $id) => $query->whereHas('journal', fn ($journal) => $journal->where('fiscal_year_id', $id)))
            ->when($filters['start_date'] ?? null, fn ($query, $date) => $query->whereHas('journal', fn ($journal) => $journal->whereDate('journal_date', '>=', $date)))
            ->when($filters['end_date'] ?? null, fn ($query, $date) => $query->whereHas('journal', fn ($journal) => $journal->whereDate('journal_date', '<=', $date)))
            ->when($filters['account_id'] ?? null, fn ($query, $id) => $query->where('account_id', $id))
            ->when($filters['project_id'] ?? null, fn ($query, $id) => $query->where('project_id', $id))
            ->when($filters['donor_id'] ?? null, fn ($query, $id) => $query->where('donor_id', $id))
            ->when($filters['department_id'] ?? null, fn ($query, $id) => $query->where('department_id', $id))
            ->orderBy('journal_id')->orderBy('line_order');
        $rows = $query->get();
        $balances = [];
        $data = $rows->map(function (JournalLine $line) use (&$balances) {
            $accountId = (int) $line->account_id;
            $balances[$accountId] = ($balances[$accountId] ?? 0) + (float) $line->debit - (float) $line->credit;
            return [
                'id' => $line->id,
                'date' => $line->journal?->journal_date?->toDateString(),
                'journal_number' => $line->journal?->journal_number,
                'reference' => $line->journal?->reference,
                // Legacy/demo journal lines can reference a deleted COA. Keep
                // the ledger readable instead of failing the entire endpoint.
                'account_code' => $line->account?->code ?? '-',
                'account_name' => $line->account?->name ?? 'Akun Lainnya / Non-COA',
                'description' => $line->line_description,
                'debit' => $line->debit,
                'credit' => $line->credit,
                'running_balance' => round($balances[$accountId], 2),
            ];
        });
        $data = $data->filter(function (array $row) use ($request) {
            $search = trim((string) $request->input('search', ''));
            if ($search === '') return true;
            return collect([$row['date'], $row['journal_number'], $row['reference'], $row['account_code'], $row['account_name'], $row['description']])
                ->contains(fn ($value) => str_contains(strtolower((string) $value), strtolower($search)));
        })->values();
        $sort = $request->input('sort', 'newest');
        $data = $data->sort(function (array $a, array $b) use ($sort) {
            if ($sort === 'oldest') return strcmp((string) $a['date'], (string) $b['date']);
            if ($sort === 'account') return strcmp((string) $a['account_code'], (string) $b['account_code']);
            if ($sort === 'debit_high') return (float) $b['debit'] <=> (float) $a['debit'];
            if ($sort === 'credit_high') return (float) $b['credit'] <=> (float) $a['credit'];
            if ($sort === 'balance_high') return (float) $b['running_balance'] <=> (float) $a['running_balance'];
            return strcmp((string) $b['date'], (string) $a['date']);
        })->values();
        $perPage = min(max((int) $request->input('per_page', 10), 1), 100);
        $page = max(1, (int) $request->input('page', 1));
        $total = $data->count();
        return response()->json(['success' => true, 'data' => $data->forPage($page, $perPage)->values(), 'totals' => ['debit' => round((float) $rows->sum('debit'), 2), 'credit' => round((float) $rows->sum('credit'), 2), 'closing_balance' => round((float) $rows->sum('debit') - (float) $rows->sum('credit'), 2)], 'meta' => ['current_page' => $page, 'per_page' => $perPage, 'total' => $total, 'last_page' => max(1, (int) ceil($total / $perPage))]]);
    }
}
