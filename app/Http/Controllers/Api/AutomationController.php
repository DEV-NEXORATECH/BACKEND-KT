<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Expense\ExpenseRequest;
use App\Models\Notification;
use App\Models\Procurement\SupplierInvoice;
use Illuminate\Http\JsonResponse;
class AutomationController extends Controller
{
    public function run(): JsonResponse
    {
        $created = 0;
        $expenses = ExpenseRequest::where('status','submitted')->where('submitted_at','<',now()->subDays(2))->get();
        foreach ($expenses as $expense) {
            $exists = Notification::whereNull('user_id')->where('action_url','/expenses-approvals/approvals')->where('message','like',"%{$expense->request_number}%")->where('created_at','>=',now()->startOfDay())->exists();
            if (!$exists) { Notification::create(['user_id'=>null,'title'=>'Approval reminder','message'=>"Expense {$expense->request_number} menunggu approval lebih dari 2 hari.",'type'=>'alert','action_url'=>'/expenses-approvals/approvals']); $created++; }
        }
        $invoices = SupplierInvoice::whereIn('status',['posted','matched'])->whereNotNull('due_date')->whereDate('due_date','<',today())->get();
        foreach ($invoices as $invoice) {
            $exists = Notification::whereNull('user_id')->where('action_url','/accounting/accounts-payable')->where('message','like',"%{$invoice->invoice_number}%")->where('created_at','>=',now()->startOfDay())->exists();
            if (!$exists) { Notification::create(['user_id'=>null,'title'=>'AP overdue reminder','message'=>"Invoice {$invoice->invoice_number} sudah melewati due date.",'type'=>'alert','action_url'=>'/accounting/accounts-payable']); $created++; }
        }
        return response()->json(['success'=>true,'message'=>"Automation selesai. {$created} reminder dibuat.",'data'=>['created'=>$created,'checked_expenses'=>$expenses->count(),'checked_invoices'=>$invoices->count()]]);
    }
}
