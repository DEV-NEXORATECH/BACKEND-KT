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

        $activities = Activity::query()
            ->with('project:id,code,name')
            ->when($filters['project_id'] ?? null, fn (Builder $q, $id) => $q->where('project_id', $id))
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
            ])->values();

        $logframes = ProjectLogframe::query()
            ->with('project:id,code,name')
            ->when($filters['project_id'] ?? null, fn (Builder $q, $id) => $q->where('project_id', $id))
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
            ->merge(GrantReportingDeadline::query()->with('grantAgreement:id,grant_no,agreement_name')->whereDate('due_date', '>=', now()->toDateString())->orderBy('due_date')->limit(20)->get()->map(fn ($deadline) => [
                'type' => 'grant_reporting', 'code' => $deadline->report_type, 'name' => $deadline->grantAgreement?->grant_no ?: $deadline->grantAgreement?->agreement_name, 'start_date' => $deadline->due_date?->toDateString(), 'status' => $deadline->status,
            ]))->values();

        $alerts = $items->filter(fn (array $item) => (float) ($item['available'] ?? 0) < 0 || (float) ($item['utilization_percent'] ?? 0) >= 25)
            ->map(function (array $item) {
                $utilization = (float) ($item['utilization_percent'] ?? 0);
                $over = (float) ($item['available'] ?? 0) < 0 || ($item['status'] ?? null) === 'over_budget';
                $level = $over || $utilization > 100 ? ['label' => 'Overbudget', 'threshold' => '>100%', 'color' => '#a51f24']
                    : ($utilization >= 100 ? ['label' => 'Habis', 'threshold' => '100%', 'color' => '#c64d53']
                        : ($utilization >= 75 ? ['label' => 'Waspada', 'threshold' => '75%', 'color' => '#d39212']
                            : ($utilization >= 50 ? ['label' => 'Normal', 'threshold' => '50%', 'color' => '#087dcc'] : ['label' => 'Aman', 'threshold' => '25%', 'color' => '#359b22'])));
                return [...$item, 'alert_level' => $level, 'recommendation' => $over ? 'Ajukan realokasi atau hentikan komitmen baru pada budget line ini.' : 'Pantau sisa saldo dan komitmen kegiatan berikutnya.'];
            })->values();

        return response()->json([
            'success' => true,
            'message' => 'Budget monitoring berhasil dimuat.',
            'data' => $items->values(),
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
