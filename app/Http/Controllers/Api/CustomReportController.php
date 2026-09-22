<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Accounting\JournalLine;
use App\Models\Expense\ExpenseRequest;
use App\Models\Procurement\PurchaseRequest;
use App\Models\Procurement\SupplierInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
class CustomReportController extends Controller
{
    public function build(Request $request): JsonResponse
    {
        $data = $request->validate(['report_type' => ['required','in:expense,project,budget,procurement'], 'start_date' => ['nullable','date'], 'end_date' => ['nullable','date'], 'project_id' => ['nullable','integer','exists:projects,id']]);
        $start = $data['start_date'] ?? now()->startOfYear()->toDateString(); $end = $data['end_date'] ?? now()->toDateString();
        if ($data['report_type'] === 'expense') {
            $rows = ExpenseRequest::with('requester:id,name')->whereBetween('request_date', [$start,$end])->when($data['project_id'] ?? null, fn($q,$id)=>$q->where('project_id',$id))->get()->map(fn($e)=>['request_number'=>$e->request_number,'date'=>$e->request_date?->toDateString(),'requester'=>$e->requester?->name,'status'=>$e->status,'type'=>$e->expense_type,'amount'=>(float)$e->total_amount]);
        } elseif ($data['report_type'] === 'procurement') {
            $rows = SupplierInvoice::with('vendor:id,name')->whereBetween('invoice_date', [$start,$end])->get()->map(fn($i)=>['invoice_number'=>$i->invoice_number,'date'=>$i->invoice_date?->toDateString(),'vendor'=>$i->vendor?->name,'status'=>$i->status,'amount'=>(float)$i->total_amount,'outstanding'=>max(0,(float)$i->total_amount-(float)$i->paid_amount)]);
        } else {
            $rows = JournalLine::with(['journal:id,journal_date,status','project:id,code,name','budgetLine:id,line_code,description'])->whereHas('journal',fn(Builder $q)=>$q->where('status','posted')->whereBetween('journal_date',[$start,$end]))->when($data['project_id'] ?? null,fn($q,$id)=>$q->where('project_id',$id))->get()->map(fn($l)=>['date'=>$l->journal?->journal_date?->toDateString(),'project'=>$l->project?->name,'budget_line'=>$l->budgetLine?->line_code,'description'=>$l->line_description,'debit'=>(float)$l->debit,'credit'=>(float)$l->credit]);
        }
        return response()->json(['success'=>true,'filters'=>['report_type'=>$data['report_type'],'start_date'=>$start,'end_date'=>$end,'project_id'=>$data['project_id'] ?? null],'totals'=>['rows'=>$rows->count(),'amount'=>round((float)$rows->sum(fn($r)=>(float)($r['amount'] ?? $r['debit'] ?? 0)),2)],'data'=>$rows->values()]);
    }
}
