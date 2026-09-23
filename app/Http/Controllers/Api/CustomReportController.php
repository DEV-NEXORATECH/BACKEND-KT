<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Accounting\JournalLine;
use App\Models\Expense\ExpenseRequest;
use App\Models\Procurement\SupplierInvoice;
use App\Models\ReportDefinition;
use App\Models\ReportChartType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Database\Eloquent\Builder;
use App\Services\Rbac\DataScopeService;
class CustomReportController extends Controller
{
    public function options(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'chart_types' => ReportChartType::query()->where('enabled', true)->orderBy('sort_order')->get(['value', 'label']),
            'reports' => ReportDefinition::query()->where('enabled', true)->orderBy('sort_order')->get(['slug', 'label', 'dimensions', 'metrics'])->map(fn (ReportDefinition $definition) => [
                'value' => $definition->slug,
                'label' => $definition->label,
                'dimensions' => $definition->dimensions,
                'metrics' => $definition->metrics,
            ])->values(),
        ]);
    }

    public function build(Request $request): JsonResponse
    {
        return response()->json(['success' => true, ...$this->reportPayload($request)]);
    }

    public function export(Request $request): StreamedResponse
    {
        $payload = $this->reportPayload($request);
        $rows = collect($payload['data']);
        $headings = $rows->flatMap(fn (array $row) => array_keys($row))->unique()->values()->all();
        $slug = $payload['filters']['report_type'];

        \App\Models\AuditLog::create([
            'user_id' => $request->user()->id,
            'module' => 'reports',
            'platform' => strtolower($request->header('X-Client-Platform', 'web')),
            'action' => 'EXPORT',
            'entity_type' => ReportDefinition::class,
            'entity_id' => ReportDefinition::query()->where('slug', $slug)->value('id'),
            'previous_values' => null,
            'new_values' => ['report_type' => $slug, 'filters' => $payload['filters'], 'rows' => $rows->count(), 'format' => 'csv'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->streamDownload(function () use ($headings, $rows) {
            $out = fopen('php://output', 'wb');
            // UTF-8 BOM keeps Indonesian headers and names readable in Excel.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headings);
            foreach ($rows as $row) {
                fputcsv($out, collect($headings)->map(fn (string $key) => $row[$key] ?? null)->all());
            }
            fclose($out);
        }, "report-{$slug}-".now()->format('YmdHis').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function reportPayload(Request $request): array
    {
        $data = $request->validate(['report_type' => ['required','exists:report_definitions,slug'], 'start_date' => ['nullable','date'], 'end_date' => ['nullable','date'], 'project_id' => ['nullable','integer','exists:projects,id']]);
        $definition = ReportDefinition::query()->where('slug', $data['report_type'])->where('enabled', true)->firstOrFail();
        $start = $data['start_date'] ?? now()->startOfYear()->toDateString(); $end = $data['end_date'] ?? now()->toDateString();
        $scope = app(DataScopeService::class);
        if ($definition->source === 'expense') {
            $query = ExpenseRequest::with('requester:id,name')->whereBetween('request_date', [$start,$end])->when($data['project_id'] ?? null, fn($q,$id)=>$q->where('project_id',$id));
            $scope->applyScope($query, $request->user(), 'requester_id', null, null);
            $rows = $query->get()->map(fn($e)=>['request_number'=>$e->request_number,'date'=>$e->request_date?->toDateString(),'requester'=>$e->requester?->name,'status'=>$e->status,'type'=>$e->expense_type,'amount'=>(float)$e->total_amount]);
        } elseif ($definition->source === 'procurement') {
            // Procurement/AP reporting is based on financially valid invoices
            // only; matched drafts are operational documents, not actuals.
            $query = SupplierInvoice::with('vendor:id,name')
                ->whereIn('status', ['posted', 'paid'])
                ->whereBetween('invoice_date', [$start,$end])
                ->when($data['project_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('purchaseOrder.purchaseRequest', fn (Builder $pr) => $pr->where('project_id', $id)));
            $scope->applyRelatedProjectScope($query, $request->user(), 'created_by', 'purchaseOrder.purchaseRequest');
            $rows = $query->get()->map(fn($i)=>['invoice_number'=>$i->invoice_number,'date'=>$i->invoice_date?->toDateString(),'vendor'=>$i->vendor?->name,'status'=>$i->status,'amount'=>(float)$i->total_amount,'outstanding'=>max(0,(float)$i->total_amount-(float)$i->paid_amount)]);
        } else {
            $query = JournalLine::with(['journal:id,journal_date,status,created_by','project:id,code,name','budgetLine:id,line_code,description'])->whereHas('journal',fn(Builder $q)=>$q->where('status','posted')->whereBetween('journal_date',[$start,$end]))->when($data['project_id'] ?? null,fn($q,$id)=>$q->where('project_id',$id));
            if (! $scope->canAccessAll($request->user())) {
                $projectIds = $scope->accessibleProjectIds($request->user());
                $query->where(function (Builder $scoped) use ($request, $projectIds) {
                    $scoped->whereHas('journal', fn (Builder $journal) => $journal->where('created_by', $request->user()->id));
                    if ($projectIds !== []) {
                        $scoped->orWhereIn('project_id', $projectIds);
                    }
                });
            }
            $rows = $query->get()->map(fn($l)=>['date'=>$l->journal?->journal_date?->toDateString(),'project'=>$l->project?->name,'budget_line'=>$l->budgetLine?->line_code,'description'=>$l->line_description,'debit'=>(float)$l->debit,'credit'=>(float)$l->credit]);
        }
        return ['filters'=>['report_type'=>$data['report_type'],'start_date'=>$start,'end_date'=>$end,'project_id'=>$data['project_id'] ?? null],'totals'=>['rows'=>$rows->count(),'amount'=>round((float)$rows->sum(fn($r)=>(float)($r['amount'] ?? $r['debit'] ?? 0)),2)],'data'=>$rows->values()->all()];
    }
}
