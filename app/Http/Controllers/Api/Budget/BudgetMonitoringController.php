<?php

namespace App\Http\Controllers\Api\Budget;

use App\Http\Controllers\Controller;
use App\Services\Budget\BudgetMonitoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BudgetMonitoringController extends Controller
{
    public function index(Request $request, BudgetMonitoringService $service): JsonResponse
    {
        $items = $service->summary($request->only([
            'budget_line_id',
            'project_id',
            'grant_agreement_id',
            'budget_category_id',
            'search',
        ]));

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
