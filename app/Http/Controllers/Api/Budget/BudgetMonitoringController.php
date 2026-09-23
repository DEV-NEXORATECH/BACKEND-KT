<?php

namespace App\Http\Controllers\Api\Budget;

use App\Http\Controllers\Controller;
use App\Models\Master\BudgetLine;
use App\Services\Budget\BudgetMonitoringService;
use App\Services\Rbac\DataScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BudgetMonitoringController extends Controller
{
    public function index(Request $request, BudgetMonitoringService $service): JsonResponse
    {
        $filters = $request->only([
            'budget_line_id',
            'project_id',
            'grant_agreement_id',
            'budget_category_id',
            'search',
        ]);

        $query = BudgetLine::query()
            ->when($filters['budget_line_id'] ?? null, fn (Builder $q, $id) => $q->whereKey($id))
            ->when($filters['project_id'] ?? null, fn (Builder $q, $id) => $q->where('project_id', $id))
            ->when($filters['grant_agreement_id'] ?? null, fn (Builder $q, $id) => $q->where('grant_agreement_id', $id))
            ->when($filters['budget_category_id'] ?? null, fn (Builder $q, $id) => $q->where('budget_category_id', $id));

        app(DataScopeService::class)->applyScope($query, $request->user(), 'created_by', 'project_id', null, []);

        $filters['budget_line_ids'] = $query->pluck('id')->all();

        $items = $service->summary($filters);

        $totals = [
            'approved_budget' => round($items->sum('approved_budget'), 2),
            'actual' => round($items->sum('actual'), 2),
            'committed' => round($items->sum('committed'), 2),
            'available' => round($items->sum('available'), 2),
        ];
        $totals['utilization_percent'] = $totals['approved_budget'] > 0
            ? round((($totals['actual'] + $totals['committed']) / $totals['approved_budget']) * 100, 2)
            : 0;

        return response()->json([
            'success' => true,
            'message' => 'Budget monitoring berhasil dimuat.',
            'data' => $items->values(),
            'totals' => $totals,
        ]);
    }

    public function validateBudget(Request $request, BudgetMonitoringService $service): JsonResponse
    {
        $data = $request->validate([
            'budget_line_id' => ['required', 'integer', 'exists:budget_lines,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Validasi budget selesai.',
            'data' => $service->validate((int) $data['budget_line_id'], (float) $data['amount']),
        ]);
    }
}
