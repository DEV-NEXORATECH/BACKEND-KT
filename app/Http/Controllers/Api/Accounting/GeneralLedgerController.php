<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\JournalLine;
use Illuminate\Http\Request;

class GeneralLedgerController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['start_date' => 'nullable|date', 'end_date' => 'nullable|date', 'account_id' => 'nullable|exists:chart_of_accounts,id', 'project_id' => 'nullable|exists:projects,id', 'donor_id' => 'nullable|exists:donors,id', 'department_id' => 'nullable|exists:departments,id']);
        $query = JournalLine::with(['journal:id,journal_number,journal_date,reference,description,status', 'account:id,code,name'])
            ->whereHas('journal', fn ($journal) => $journal->where('status', 'posted'))
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
            return ['id' => $line->id, 'date' => $line->journal->journal_date?->toDateString(), 'journal_number' => $line->journal->journal_number, 'reference' => $line->journal->reference, 'account_code' => $line->account->code, 'account_name' => $line->account->name, 'description' => $line->line_description, 'debit' => $line->debit, 'credit' => $line->credit, 'running_balance' => round($balances[$accountId], 2)];
        });
        return response()->json(['success' => true, 'data' => $data, 'totals' => ['debit' => round((float) $rows->sum('debit'), 2), 'credit' => round((float) $rows->sum('credit'), 2), 'closing_balance' => round((float) $rows->sum('debit') - (float) $rows->sum('credit'), 2)]]);
    }
}
