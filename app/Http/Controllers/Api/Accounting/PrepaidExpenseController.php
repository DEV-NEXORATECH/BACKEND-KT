<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\PrepaidExpense;
use App\Models\Master\ChartOfAccount;
use Illuminate\Http\Request;

class PrepaidExpenseController extends Controller
{
    public function index(Request $request)
    {
        $rows = PrepaidExpense::with(['prepaidAccount:id,code,name', 'expenseAccount:id,code,name'])
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($x) => $x->where('reference', 'like', '%'.$request->search.'%')->orWhere('description', 'like', '%'.$request->search.'%')))
            ->latest()->paginate(min(100, max(1, (int) $request->get('per_page', 10))));
        return response()->json(['success' => true, 'data' => $rows->items(), 'meta' => ['current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'total' => $rows->total()]]);
    }

    public function options()
    {
        return response()->json(['success' => true, 'data' => ChartOfAccount::where('is_header', false)->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'account_type'])]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['reference' => 'required|string|max:80|unique:prepaid_expenses,reference', 'description' => 'required|string|max:255', 'start_date' => 'required|date', 'periods' => 'required|integer|min:1|max:120', 'total_amount' => 'required|numeric|min:0.01', 'prepaid_account_id' => 'nullable|exists:chart_of_accounts,id', 'expense_account_id' => 'nullable|exists:chart_of_accounts,id', 'status' => 'nullable|in:active,completed,on_hold']);
        $data['created_by'] = $request->user()?->id;
        return response()->json(['success' => true, 'data' => PrepaidExpense::create($data)], 201);
    }

    public function update(Request $request, PrepaidExpense $prepaidExpense)
    {
        $data = $request->validate(['reference' => 'required|string|max:80|unique:prepaid_expenses,reference,'.$prepaidExpense->id, 'description' => 'required|string|max:255', 'start_date' => 'required|date', 'periods' => 'required|integer|min:1|max:120', 'total_amount' => 'required|numeric|min:0.01', 'prepaid_account_id' => 'nullable|exists:chart_of_accounts,id', 'expense_account_id' => 'nullable|exists:chart_of_accounts,id', 'status' => 'nullable|in:active,completed,on_hold']);
        $prepaidExpense->update($data);
        return response()->json(['success' => true, 'data' => $prepaidExpense->fresh()]);
    }

    public function destroy(PrepaidExpense $prepaidExpense) { $prepaidExpense->delete(); return response()->json(['success' => true]); }

    public function schedule(PrepaidExpense $prepaidExpense)
    {
        $monthly = round((float) $prepaidExpense->total_amount / $prepaidExpense->periods, 2);
        return response()->json(['success' => true, 'data' => collect(range(1, $prepaidExpense->periods))->map(fn ($n) => ['period' => $n, 'date' => $prepaidExpense->start_date->copy()->addMonths($n - 1)->toDateString(), 'amount' => $n === $prepaidExpense->periods ? round((float) $prepaidExpense->total_amount - ($monthly * ($n - 1)), 2) : $monthly, 'status' => $n <= (int) round((float) $prepaidExpense->amortized_amount / max($monthly, 1)) ? 'posted' : 'scheduled'])->values()]);
    }
}
