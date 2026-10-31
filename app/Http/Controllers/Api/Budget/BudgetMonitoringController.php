<?php

namespace App\Http\Controllers\Api\Budget;

use App\Http\Controllers\Controller;
use App\Models\Master\BudgetLine;
use App\Models\Master\Activity;
use App\Models\Master\GrantReportingDeadline;
use App\Models\Master\ProjectLogframe;
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
        $filters['fiscal_year_id'] = $request->query('fiscal_year_id');

        $query = BudgetLine::query()
            ->where('line_code', 'not like', 'AI%')
            ->when($filters['budget_line_id'] ?? null, fn (Builder $q, $id) => $q->whereKey($id))
            ->when($filters['project_id'] ?? null, fn (Builder $q, $id) => $q->where('project_id', $id))
            ->when($filters['grant_agreement_id'] ?? null, fn (Builder $q, $id) => $q->where('grant_agreement_id', $id))
            ->when($filters['budget_category_id'] ?? null, fn (Builder $q, $id) => $q->where('budget_category_id', $id));

        app(DataScopeService::class)->applyScope($query, $request->user(), 'created_by', 'project_id', null, []);
        if ($request->user()) {
            app(DataScopeService::class)->applyScope($query, $request->user(), 'created_by', 'project_id', null, []);
        }

        $filters['budget_line_ids'] = $query->pluck('id')->all();

        $items = $service->summary($filters);

        $totals = [
            'approved_budget' => round($items->sum('approved_budget'), 2),
            'actual' => round($items->sum('actual'), 2),
            'committed' => round($items->sum('committed'), 2),
            'available' => round($items->sum('available'), 2),
            'variance' => round($items->sum('variance'), 2),
            'carried_balance' => round($items->sum('carried_balance'), 2),
        ];
        $totals['utilization_percent'] = $totals['approved_budget'] > 0
            ? round((($totals['actual'] + $totals['committed']) / $totals['approved_budget']) * 100, 2)
            : 0;

        // Grouping BvA by Activity
        $byActivity = $items->groupBy(fn ($it) => $it['activity_name'] ?: 'General Activity')
            ->map(function ($group, $name) {
                $approved = round($group->sum('approved_budget'), 2);
                $actual = round($group->sum('actual'), 2);
                $committed = round($group->sum('committed'), 2);
                $totalSpent = round($actual + $committed, 2);
                $variance = round($approved - $actual, 2);
                $carried = round($approved - $totalSpent, 2);
                $utilization = $approved > 0 ? round(($totalSpent / $approved) * 100, 2) : 0;
                $first = $group->first();

                return [
                    'activity_name' => $name,
                    'activity_code' => $first['activity_code'] ?? null,
                    'sub_output_name' => $first['sub_output_name'] ?? null,
                    'approved_budget' => $approved,
                    'actual' => $actual,
                    'committed' => $committed,
                    'total_spent' => $totalSpent,
                    'variance' => $variance,
                    'carried_balance' => $carried,
                    'utilization_percent' => $utilization,
                    'status' => $carried < 0 ? 'over_budget' : ($utilization >= 90 ? 'warning' : 'healthy'),
                    'lines_count' => $group->count(),
                ];
            })->values();

        // Grouping BvA by Sub-Output
        $bySubOutput = $items->groupBy(fn ($it) => $it['sub_output_name'] ?: 'General Framework')
            ->map(function ($group, $name) {
                $approved = round($group->sum('approved_budget'), 2);
                $actual = round($group->sum('actual'), 2);
                $committed = round($group->sum('committed'), 2);
                $totalSpent = round($actual + $committed, 2);
                $variance = round($approved - $actual, 2);
                $carried = round($approved - $totalSpent, 2);
                $utilization = $approved > 0 ? round(($totalSpent / $approved) * 100, 2) : 0;
                $first = $group->first();

                return [
                    'sub_output_name' => $name,
                    'sub_output_code' => $first['sub_output_code'] ?? null,
                    'approved_budget' => $approved,
                    'actual' => $actual,
                    'committed' => $committed,
                    'total_spent' => $totalSpent,
                    'variance' => $variance,
                    'carried_balance' => $carried,
                    'utilization_percent' => $utilization,
                    'status' => $carried < 0 ? 'over_budget' : ($utilization >= 90 ? 'warning' : 'healthy'),
                    'lines_count' => $group->count(),
                ];
            })->values();

        $activities = Activity::query()
            ->with('project:id,code,name')
            ->when($filters['project_id'] ?? null, fn (Builder $q, $id) => $q->where('project_id', $id))
            ->when($filters['fiscal_year_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('project.fiscalYears', fn (Builder $fiscalQuery) => $fiscalQuery->whereKey($id)))
            ->where('is_active', true)
            ->orderBy('start_date')
            ->get()
            ->map(fn (Activity $activity) => [
                'id' => $activity->id,
                'code' => $activity->code,
                'name' => $activity->name,
                'project' => $activity->project?->name,
                'project_code' => $activity->project?->code,
                'pic_name' => $activity->pic_name,
                'start_date' => $activity->start_date?->toDateString(),
                'end_date' => $activity->end_date?->toDateString(),
                'target_output' => $activity->target_output,
                'budget_holder' => $activity->project?->manager_name,
            ])->values();

        $logframes = ProjectLogframe::query()
            ->with('project:id,code,name')
            ->when($filters['project_id'] ?? null, fn (Builder $q, $id) => $q->where('project_id', $id))
            ->when($filters['fiscal_year_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('project.fiscalYears', fn (Builder $fiscalQuery) => $fiscalQuery->whereKey($id)))
            ->where('is_active', true)
            ->latest('id')
            ->get()
            ->map(fn (ProjectLogframe $item) => [
                'id' => $item->id,
                'project' => $item->project?->name,
                'project_code' => $item->project?->code,
                'level' => $item->level,
                'code' => $item->code,
                'description' => $item->description,
                'indicator' => $item->indicator,
                'baseline' => $item->baseline,
                'target' => $item->target,
                'actual' => $item->actual,
                'unit' => $item->unit,
            ])->values();

        $upcomingEvents = collect($activities)->filter(fn (array $activity) => $activity['start_date'] && $activity['start_date'] >= now()->toDateString())->take(20)->values()
            ->merge(GrantReportingDeadline::query()->with('grantAgreement:id,grant_no,agreement_name')->when($filters['fiscal_year_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('grantAgreement', fn (Builder $grantQuery) => $grantQuery->where('fiscal_year_id', $id)))->whereDate('due_date', '>=', now()->toDateString())->orderBy('due_date')->limit(20)->get()->map(fn ($deadline) => [
                'type' => 'grant_reporting', 'code' => $deadline->report_type, 'name' => $deadline->grantAgreement?->grant_no ?: $deadline->grantAgreement?->agreement_name, 'start_date' => $deadline->due_date?->toDateString(), 'status' => $deadline->status,
            ]))->values();

        $alerts = $items->filter(fn (array $item) => (float) ($item['available'] ?? 0) < 0 || (float) ($item['utilization_percent'] ?? 0) >= 25)
            ->map(function (array $item) {
                $utilization = (float) ($item['utilization_percent'] ?? 0);
                $over = (float) ($item['available'] ?? 0) < 0 || ($item['status'] ?? null) === 'over_budget';
                if ($over || $utilization >= 110) {
                    $level = ['label' => 'Overbudget', 'threshold' => '110%', 'color' => '#A51F24'];
                } elseif ($utilization >= 100) {
                    $level = ['label' => 'Kritis', 'threshold' => '100%', 'color' => '#D35400'];
                } elseif ($utilization >= 75) {
                    $level = ['label' => 'Waspada', 'threshold' => '75%', 'color' => '#D39212'];
                } elseif ($utilization >= 50) {
                    $level = ['label' => 'Normal', 'threshold' => '50%', 'color' => '#087DCC'];
                } else {
                    $level = ['label' => 'Aman', 'threshold' => '25%', 'color' => '#359B22'];
                }
                return [...$item, 'alert_level' => $level, 'recommendation' => $over ? 'Ajukan realokasi atau hentikan komitmen baru pada budget line ini.' : 'Pantau sisa saldo dan komitmen kegiatan berikutnya.'];
            })->values();

        return response()->json([
            'success' => true,
            'message' => 'Budget monitoring berhasil dimuat.',
            'data' => $items->values(),
            'by_line' => $items->values(),
            'by_activity' => $byActivity,
            'by_sub_output' => $bySubOutput,
            'totals' => $totals,
            'activities' => $activities,
            'upcoming_events' => $upcomingEvents,
            'logframes' => $logframes,
            'alerts' => $alerts,
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
