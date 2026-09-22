<?php
namespace App\Http\Controllers\Api\Procurement;
use App\Http\Controllers\Controller;
use App\Models\Procurement\SupplierContractNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
class SupplierContractNotificationController extends Controller
{
    public function index(): JsonResponse { return response()->json(['success' => true, 'data' => SupplierContractNotification::with(['vendor:id,code,name','purchaseRequest:id,pr_number','purchaseOrder:id,po_number'])->latest('id')->get()]); }
    public function store(Request $request): JsonResponse { $data = $request->validate(['purchase_request_id' => ['nullable','integer','exists:purchase_requests,id'], 'purchase_order_id' => ['nullable','integer','exists:purchase_orders,id'], 'vendor_id' => ['nullable','integer','exists:vendors,id'], 'notification_date' => ['required','date'], 'subject' => ['required','string','max:200'], 'notes' => ['nullable','string']]); $item = SupplierContractNotification::create([...$data, 'scn_number' => 'SCN-'.now()->format('YmdHis').'-'.random_int(100,999), 'created_by' => $request->user()->id]); return response()->json(['success'=>true,'message'=>'SCN berhasil dibuat.','data'=>$item->load(['vendor:id,code,name','purchaseRequest:id,pr_number','purchaseOrder:id,po_number'])], Response::HTTP_CREATED); }
    public function issue(SupplierContractNotification $supplierContractNotification): JsonResponse { if ($supplierContractNotification->status !== 'draft') throw ValidationException::withMessages(['status'=>'SCN hanya dapat diterbitkan dari draft.']); $supplierContractNotification->update(['status'=>'issued','issued_at'=>now()]); return response()->json(['success'=>true,'message'=>'SCN berhasil diterbitkan.','data'=>$supplierContractNotification->fresh()]); }
    public function cancel(SupplierContractNotification $supplierContractNotification): JsonResponse { if (!in_array($supplierContractNotification->status, ['draft','issued'], true)) throw ValidationException::withMessages(['status'=>'SCN tidak dapat dibatalkan dari status saat ini.']); $supplierContractNotification->update(['status'=>'cancelled']); return response()->json(['success'=>true,'message'=>'SCN berhasil dibatalkan.','data'=>$supplierContractNotification->fresh()]); }
}
