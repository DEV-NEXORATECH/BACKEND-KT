<?php

namespace App\Http\Controllers\Api\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Procurement\GoodsReceipt;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseRequest;
use App\Models\Procurement\SupplierInvoice;
use App\Models\Finance\Payment;
use App\Models\Procurement\PoAmendment;
use App\Models\Procurement\VendorEvaluation;
use App\Services\Rbac\DataScopeService;
use App\Services\Approval\ApprovalWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ProcurementFulfillmentController extends Controller
{
    public function amendments(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->ensureRelatedScope($request, PurchaseOrder::class, $purchaseOrder->id, 'created_by', 'purchaseRequest');
        return response()->json(['success' => true, 'data' => $purchaseOrder->amendments()->latest('version')->get()]);
    }

    public function createAmendment(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->ensureRelatedScope($request, PurchaseOrder::class, $purchaseOrder->id, 'created_by', 'purchaseRequest');
        if ($purchaseOrder->status !== 'approved') throw ValidationException::withMessages(['status' => 'Amendment hanya dapat dibuat untuk PO approved.']);
        $data = $request->validate(['contract_number' => ['nullable', 'string', 'max:80'], 'contract_date' => ['nullable', 'date'], 'terms' => ['nullable', 'string'], 'reason' => ['required', 'string', 'max:2000']]);
        $changed = collect(['contract_number', 'contract_date', 'terms'])->filter(fn ($field) => array_key_exists($field, $data) && (string) $purchaseOrder->getAttribute($field) !== (string) $data[$field])->mapWithKeys(fn ($field) => [$field => ['old' => $purchaseOrder->getAttribute($field), 'new' => $data[$field]]])->all();
        if ($changed === []) throw ValidationException::withMessages(['changes' => 'Tidak ada perubahan kontrak yang diajukan.']);
        $version = (int) $purchaseOrder->amendments()->max('version') + 1;
        $amendment = $purchaseOrder->amendments()->create(['amendment_number' => $purchaseOrder->po_number.'-AMD-'.str_pad((string) $version, 2, '0', STR_PAD_LEFT), 'version' => $version, 'changes' => $changed, 'reason' => $data['reason'], 'status' => 'pending_approval', 'created_by' => $request->user()->id]);
        return response()->json(['success' => true, 'message' => 'PO amendment diajukan untuk approval.', 'data' => $amendment], Response::HTTP_CREATED);
    }

    public function approveAmendment(Request $request, PoAmendment $poAmendment): JsonResponse
    {
        $po = $poAmendment->purchaseOrder;
        $this->ensureRelatedScope($request, PurchaseOrder::class, $po->id, 'created_by', 'purchaseRequest');
        if ($poAmendment->status !== 'pending_approval') throw ValidationException::withMessages(['status' => 'Amendment tidak menunggu approval.']);
        $changes = collect($poAmendment->changes)->mapWithKeys(fn ($value, $field) => [$field => $value['new']])->all();
        DB::transaction(function () use ($po, $poAmendment, $changes, $request) { $po->update([...$changes, 'updated_by' => $request->user()->id]); $poAmendment->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]); });
        return response()->json(['success' => true, 'message' => 'PO amendment berhasil diapprove.', 'data' => $poAmendment->fresh()]);
    }

    public function evaluateVendor(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->ensureRelatedScope($request, PurchaseOrder::class, $purchaseOrder->id, 'created_by', 'purchaseRequest');
        $data = $request->validate(['goods_receipt_id' => ['nullable', 'integer', 'exists:goods_receipts,id'], 'quality_score' => ['required', 'integer', 'between:1,5'], 'delivery_score' => ['required', 'integer', 'between:1,5'], 'price_score' => ['required', 'integer', 'between:1,5'], 'service_score' => ['required', 'integer', 'between:1,5'], 'evaluator_notes' => ['nullable', 'string', 'max:2000']]);
        if (! $purchaseOrder->vendor_id) throw ValidationException::withMessages(['vendor' => 'PO belum memiliki vendor.']);
        if (! empty($data['goods_receipt_id']) && ! $purchaseOrder->goodsReceipts()->whereKey($data['goods_receipt_id'])->exists()) throw ValidationException::withMessages(['goods_receipt_id' => 'GRN harus berasal dari PO yang sama.']);
        $data['vendor_id'] = $purchaseOrder->vendor_id; $data['purchase_order_id'] = $purchaseOrder->id; $data['overall_score'] = round(collect(['quality_score','delivery_score','price_score','service_score'])->avg(fn ($key) => $data[$key]), 1); $data['evaluated_by'] = $request->user()->id;
        $evaluation = VendorEvaluation::create($data);
        return response()->json(['success' => true, 'message' => 'Evaluasi vendor berhasil dicatat.', 'data' => $evaluation->load('vendor:id,code,name')], Response::HTTP_CREATED);
    }
    public function processOptions(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => [
            ['value' => 'pr', 'title' => 'Purchase Request', 'subtitle' => 'Ajukan dan pantau permintaan pembelian.', 'endpoint' => '/v1/procurement/purchase-requests', 'columns' => [['key' => 'pr_number', 'label' => 'PR Number'], ['key' => 'request_date', 'label' => 'Date'], ['key' => 'status', 'label' => 'Status'], ['key' => 'total_amount', 'label' => 'Amount']]],
            ['value' => 'po', 'title' => 'Purchase Orders', 'subtitle' => 'Manage purchase orders created from approved purchase requests.', 'endpoint' => '/v1/procurement/purchase-orders', 'columns' => [['key' => 'po_number', 'label' => 'PO Number'], ['key' => 'po_date', 'label' => 'Date'], ['key' => 'vendor', 'label' => 'Vendor'], ['key' => 'status', 'label' => 'Status']]],
            ['value' => 'grn', 'title' => 'Goods Receipts', 'subtitle' => 'Record goods received against a purchase order.', 'endpoint' => '/v1/procurement/goods-receipts', 'columns' => [['key' => 'grn_number', 'label' => 'GRN Number'], ['key' => 'receipt_date', 'label' => 'Date'], ['key' => 'purchase_order', 'label' => 'Purchase Order'], ['key' => 'status', 'label' => 'Status']]],
            ['value' => 'invoice', 'title' => 'Supplier Invoices', 'subtitle' => 'Manage supplier invoices and 3-way matching.', 'endpoint' => '/v1/procurement/supplier-invoices', 'columns' => [['key' => 'invoice_number', 'label' => 'Invoice Number'], ['key' => 'invoice_date', 'label' => 'Date'], ['key' => 'vendor', 'label' => 'Vendor'], ['key' => 'tax', 'label' => 'Tax'], ['key' => 'match_status', 'label' => 'Match Status'], ['key' => 'status', 'label' => 'Status']]],
        ]]);
    }

    public function workflowReview(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('search', ''));
        $prQuery = PurchaseRequest::query()->whereNotIn('status', ['draft', 'cancelled']);
        $invoiceQuery = SupplierInvoice::query()->whereIn('status', ['draft', 'open']);
        app(DataScopeService::class)->applyRelatedProjectScope($prQuery, $request->user(), 'created_by', 'project');
        app(DataScopeService::class)->applyRelatedProjectScope($invoiceQuery, $request->user(), 'created_by', 'purchaseOrder.purchaseRequest');
        if ($term !== '') { $prQuery->where('pr_number', 'like', "%{$term}%"); $invoiceQuery->where('invoice_number', 'like', "%{$term}%"); }
        $items = $prQuery->latest('id')->get()->map(fn ($row) => ['id' => $row->id, 'ref' => $row->pr_number ?: '-', 'kind' => 'Purchase Request', 'status' => $row->status ?: '-'])->concat($invoiceQuery->latest('id')->get()->map(fn ($row) => ['id' => $row->id, 'ref' => $row->invoice_number ?: '-', 'kind' => 'Supplier Invoice', 'status' => $row->status ?: '-']))->sortByDesc('ref')->values();
        $meta = null; $rows = $items;
        if ($request->has('page') || $request->has('per_page')) { $perPage = min(100, max(1, (int) $request->query('per_page', 10))); $page = max(1, (int) $request->query('page', 1)); $meta = ['current_page' => $page, 'last_page' => max(1, (int) ceil($items->count() / $perPage)), 'per_page' => $perPage, 'total' => $items->count()]; $rows = $items->forPage($page, $perPage)->values(); }
        return response()->json(['success' => true, 'data' => $rows, 'meta' => $meta]);
    }

    public function goodsReceipts(Request $request): JsonResponse
    {
        $query = GoodsReceipt::with($this->grnRelations());
        app(DataScopeService::class)->applyRelatedProjectScope($query, $request->user(), 'created_by', 'purchaseOrder.purchaseRequest');
        $query->when($request->filled('search'), function ($q) use ($request) {
            $search = '%'.trim((string) $request->input('search')).'%';
            $q->where(function ($scope) use ($search) {
                $scope->where('grn_number', 'like', $search)->orWhere('description', 'like', $search)->orWhere('remarks', 'like', $search)
                    ->orWhereHas('purchaseOrder', fn ($po) => $po->where('po_number', 'like', $search));
            });
        })->when($request->input('status') === 'completed', fn ($q) => $q->whereIn('status', ['received', 'completed', 'approved']))
            ->when($request->input('status') === 'pending', fn ($q) => $q->whereNotIn('status', ['received', 'completed', 'approved']));
        $sort = $request->input('sort', 'newest');
        $rows = $query->orderBy($sort === 'oldest' ? 'receipt_date' : 'id', $sort === 'oldest' ? 'asc' : 'desc')
            ->when($request->has('page') || $request->has('per_page'), fn ($q) => $q->paginate(min(100, max(1, (int) $request->query('per_page', 10)))), fn ($q) => $q->get());
        $items = $rows instanceof \Illuminate\Pagination\LengthAwarePaginator ? $rows->items() : $rows;
        return response()->json(['success' => true, 'data' => collect($items)->map(fn (GoodsReceipt $grn) => $this->formatGrn($grn)), 'meta' => $rows instanceof \Illuminate\Pagination\LengthAwarePaginator ? ['current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'per_page' => $rows->perPage(), 'total' => $rows->total()] : null]);
    }

    public function showGoodsReceipt(Request $request, GoodsReceipt $goodsReceipt): JsonResponse
    {
        $this->ensureRelatedScope($request, GoodsReceipt::class, $goodsReceipt->id, 'created_by', 'purchaseOrder.purchaseRequest');
        return response()->json(['success' => true, 'data' => $this->formatGrn($goodsReceipt->load($this->grnRelations()))]);
    }

    public function updateGoodsReceipt(Request $request, GoodsReceipt $goodsReceipt): JsonResponse
    {
        $this->ensureRelatedScope($request, GoodsReceipt::class, $goodsReceipt->id, 'created_by', 'purchaseOrder.purchaseRequest');
        if ($goodsReceipt->status === 'cancelled') throw ValidationException::withMessages(['status' => 'GRN sudah dibatalkan.']);
        $data = $request->validate([
            'grn_number' => ['sometimes', 'string', 'max:80', 'unique:goods_receipts,grn_number,'.$goodsReceipt->id],
            'receipt_date' => ['sometimes', 'date'], 'supplier_waybill_number' => ['nullable', 'string', 'max:100'],
            'receiving_location' => ['nullable', 'string', 'max:200'], 'donor_name' => ['nullable', 'string', 'max:200'],
            'acceptable_for_payment' => ['nullable', 'boolean'], 'remarks' => ['nullable', 'string'],
            'receipt_by_name' => ['nullable', 'string', 'max:120'], 'receipt_by_position' => ['nullable', 'string', 'max:120'],
            'requester_name' => ['nullable', 'string', 'max:120'], 'requester_position' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'], 'lines' => ['sometimes', 'array', 'min:1'],
            'lines.*.purchase_order_line_id' => ['required_with:lines', 'integer', 'exists:purchase_order_lines,id'],
            'lines.*.received_quantity' => ['required_with:lines', 'numeric', 'min:0.01'],
            'lines.*.quality_check' => ['nullable', 'string', 'max:50'], 'lines.*.comment' => ['nullable', 'string'],
        ]);
        if (isset($data['lines'])) {
            $this->validateOrderLines($goodsReceipt->purchaseOrder, $data['lines'], $goodsReceipt->id);
            $goodsReceipt->lines()->get()->each->delete();
            foreach ($data['lines'] as $line) $goodsReceipt->lines()->create($line);
            unset($data['lines']);
        }
        $goodsReceipt->update($data);
        return response()->json(['success' => true, 'message' => 'GRN berhasil diperbarui.', 'data' => $this->formatGrn($goodsReceipt->fresh($this->grnRelations()))]);
    }

    public function cancelGoodsReceipt(Request $request, GoodsReceipt $goodsReceipt): JsonResponse
    {
        $this->ensureRelatedScope($request, GoodsReceipt::class, $goodsReceipt->id, 'created_by', 'purchaseOrder.purchaseRequest');
        if ($goodsReceipt->status === 'cancelled') throw ValidationException::withMessages(['status' => 'GRN sudah dibatalkan.']);
        if (SupplierInvoice::query()->where('goods_receipt_id', $goodsReceipt->id)->whereNotIn('status', ['cancelled'])->exists()) throw ValidationException::withMessages(['status' => 'GRN yang sudah direferensikan invoice tidak dapat dibatalkan.']);
        $goodsReceipt->update(['status' => 'cancelled']);
        return response()->json(['success' => true, 'message' => 'GRN berhasil dibatalkan.', 'data' => $this->formatGrn($goodsReceipt->fresh($this->grnRelations()))]);
    }

    public function supplierInvoices(Request $request): JsonResponse
    {
        $query = SupplierInvoice::with(['purchaseOrder:id,po_number', 'vendor:id,code,name']);
        app(DataScopeService::class)->applyRelatedProjectScope($query, $request->user(), 'created_by', 'purchaseOrder.purchaseRequest');
        $query->when($request->filled('search'), function ($q) use ($request) {
            $search = '%'.trim((string) $request->input('search')).'%';
            $q->where(function ($scope) use ($search) {
                $scope->where('invoice_number', 'like', $search)->orWhere('notes', 'like', $search)->orWhere('vendor_name', 'like', $search)
                    ->orWhereHas('purchaseOrder', fn ($po) => $po->where('po_number', 'like', $search));
            });
        })->when($request->input('status') === 'completed', fn ($q) => $q->whereIn('status', ['paid', 'completed', 'approved', 'matched']))
            ->when($request->input('status') === 'pending', fn ($q) => $q->whereNotIn('status', ['paid', 'completed', 'approved', 'matched']));
        $sort = $request->input('sort', 'newest');
        $rows = $query->orderBy($sort === 'oldest' ? 'invoice_date' : 'id', $sort === 'oldest' ? 'asc' : 'desc')
            ->when($request->has('page') || $request->has('per_page'), fn ($q) => $q->paginate(min(100, max(1, (int) $request->query('per_page', 10)))), fn ($q) => $q->get());
        $items = $rows instanceof \Illuminate\Pagination\LengthAwarePaginator ? $rows->items() : $rows;
        return response()->json(['success' => true, 'data' => collect($items)->map(fn ($invoice) => ['id' => $invoice->id, 'invoice_number' => $invoice->invoice_number, 'invoice_date' => $invoice->invoice_date?->toDateString(), 'purchase_order' => $invoice->purchaseOrder?->po_number, 'vendor' => $invoice->vendor?->name, 'match_status' => $invoice->match_status, 'status' => $invoice->status, 'total_amount' => $invoice->total_amount]), 'meta' => $rows instanceof \Illuminate\Pagination\LengthAwarePaginator ? ['current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'per_page' => $rows->perPage(), 'total' => $rows->total()] : null]);
    }

    public function showSupplierInvoice(Request $request, SupplierInvoice $supplierInvoice): JsonResponse
    {
        $this->ensureRelatedScope($request, SupplierInvoice::class, $supplierInvoice->id, 'created_by', 'purchaseOrder.purchaseRequest');
        return response()->json(['success' => true, 'data' => $this->formatInvoice($supplierInvoice->load(['purchaseOrder:id,po_number', 'goodsReceipt:id,grn_number', 'vendor:id,code,name', 'lines']))]);
    }

    public function updateSupplierInvoice(Request $request, SupplierInvoice $supplierInvoice): JsonResponse
    {
        $this->ensureRelatedScope($request, SupplierInvoice::class, $supplierInvoice->id, 'created_by', 'purchaseOrder.purchaseRequest');
        if (! in_array($supplierInvoice->status, ['draft', 'matched'], true)) throw ValidationException::withMessages(['status' => 'Invoice hanya dapat diubah saat draft atau matched.']);
        $data = $request->validate(['invoice_date' => ['sometimes', 'date'], 'due_date' => ['nullable', 'date'], 'tax_id' => ['nullable', 'integer', 'exists:taxes,id'], 'notes' => ['nullable', 'string'], 'lines' => ['sometimes', 'array', 'min:1'], 'lines.*.purchase_order_line_id' => ['required_with:lines', 'integer', 'exists:purchase_order_lines,id'], 'lines.*.item_description' => ['required_with:lines', 'string', 'max:255'], 'lines.*.quantity' => ['required_with:lines', 'numeric', 'min:0.01'], 'lines.*.unit_price' => ['required_with:lines', 'numeric', 'min:0.01']]);
        if (isset($data['lines'])) {
            $this->validateOrderLines($supplierInvoice->purchaseOrder, $data['lines']);
            $supplierInvoice->lines()->get()->each->delete();
            $total = 0;
            foreach ($data['lines'] as $line) { $line['total_amount'] = round((float) $line['quantity'] * (float) $line['unit_price'], 2); $total += $line['total_amount']; $supplierInvoice->lines()->create($line); }
            $data['total_amount'] = $total;
            $supplierInvoice->update(['match_status' => 'unchecked', 'status' => 'draft']);
            unset($data['lines']);
        }
        $supplierInvoice->update($data);
        return response()->json(['success' => true, 'message' => 'Supplier invoice berhasil diperbarui.', 'data' => $this->formatInvoice($supplierInvoice->fresh(['purchaseOrder:id,po_number', 'goodsReceipt:id,grn_number', 'vendor:id,code,name', 'lines']))]);
    }

    public function cancelSupplierInvoice(Request $request, SupplierInvoice $supplierInvoice): JsonResponse
    {
        $this->ensureRelatedScope($request, SupplierInvoice::class, $supplierInvoice->id, 'created_by', 'purchaseOrder.purchaseRequest');
        if (in_array($supplierInvoice->status, ['posted', 'paid', 'cancelled'], true)) throw ValidationException::withMessages(['status' => 'Invoice tidak dapat dibatalkan dari status saat ini.']);
        $supplierInvoice->update(['status' => 'cancelled']);
        return response()->json(['success' => true, 'message' => 'Supplier invoice berhasil dibatalkan.', 'data' => $this->formatInvoice($supplierInvoice->fresh(['purchaseOrder:id,po_number', 'goodsReceipt:id,grn_number', 'vendor:id,code,name', 'lines']))]);
    }

    public function purchaseOrders(Request $request): JsonResponse
    {
        $query = PurchaseOrder::with(['purchaseRequest:id,pr_number', 'vendor:id,code,name', 'lines']);
        app(DataScopeService::class)->applyRelatedProjectScope($query, $request->user(), 'created_by', 'purchaseRequest');
        $query->when($request->filled('search'), function ($q) use ($request) {
            $search = '%'.trim((string) $request->input('search')).'%';
            $q->where(function ($scope) use ($search) {
                $scope->where('po_number', 'like', $search)->orWhere('description', 'like', $search)->orWhere('supplier_company_name', 'like', $search)
                    ->orWhereHas('purchaseRequest', fn ($pr) => $pr->where('pr_number', 'like', $search));
            });
        })->when($request->input('status') === 'completed', fn ($q) => $q->whereIn('status', ['completed', 'received', 'approved']))
            ->when($request->input('status') === 'pending', fn ($q) => $q->whereNotIn('status', ['completed', 'received', 'approved']));
        $sort = $request->input('sort', 'newest');
        $rows = $query->orderBy($sort === 'oldest' ? 'po_date' : 'id', $sort === 'oldest' ? 'asc' : 'desc')
            ->when($request->has('page') || $request->has('per_page'), fn ($q) => $q->paginate(min(100, max(1, (int) $request->query('per_page', 10)))), fn ($q) => $q->get());
        $items = $rows instanceof \Illuminate\Pagination\LengthAwarePaginator ? $rows->items() : $rows;
        return response()->json([
            'success' => true,
            'data' => collect($items)->map(fn ($po) => $this->formatPo($po)),
            'meta' => $rows instanceof \Illuminate\Pagination\LengthAwarePaginator ? ['current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'per_page' => $rows->perPage(), 'total' => $rows->total()] : null,
        ]);
    }

    public function showPurchaseOrder(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->ensureRelatedScope($request, PurchaseOrder::class, $purchaseOrder->id, 'created_by', 'purchaseRequest');
        return response()->json(['success' => true, 'data' => $this->formatPo($purchaseOrder->load(['purchaseRequest:id,pr_number', 'vendor:id,code,name', 'lines']))]);
    }

    public function updatePurchaseOrder(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->ensureRelatedScope($request, PurchaseOrder::class, $purchaseOrder->id, 'created_by', 'purchaseRequest');
        if ($purchaseOrder->status !== 'draft') throw ValidationException::withMessages(['status' => 'PO hanya dapat diubah saat draft.']);
        $data = $request->validate([
            'po_date' => ['sometimes', 'date'],
            'required_delivery_date' => ['nullable', 'date'],
            'delivery_method' => ['nullable', 'string', 'max:30'],
            'payment_terms' => ['nullable', 'string', 'max:120'],
            'delivery_address' => ['nullable', 'string'],
            'delivery_contact_name' => ['nullable', 'string', 'max:120'],
            'delivery_contact_email' => ['nullable', 'email', 'max:160'],
            'supplier_company_name' => ['nullable', 'string', 'max:180'],
            'supplier_contact_name' => ['nullable', 'string', 'max:120'],
            'supplier_email' => ['nullable', 'email', 'max:160'],
            'supplier_phone' => ['nullable', 'string', 'max:50'],
            'supplier_fax' => ['nullable', 'string', 'max:50'],
            'supplier_mobile' => ['nullable', 'string', 'max:50'],
            'supplier_address' => ['nullable', 'string'],
            'procurement_contact_name' => ['nullable', 'string', 'max:120'],
            'procurement_contact_email' => ['nullable', 'email', 'max:160'],
            'procurement_address' => ['nullable', 'string'],
            'other_tax' => ['nullable', 'numeric', 'min:0'],
            'other_charges' => ['nullable', 'numeric', 'min:0'],
            'checked_by' => ['nullable', 'string', 'max:120'],
            'checked_by_title' => ['nullable', 'string', 'max:120'],
            'budget_holder_name' => ['nullable', 'string', 'max:120'],
            'budget_holder_title' => ['nullable', 'string', 'max:120'],
            'supplier_acceptance_name' => ['nullable', 'string', 'max:120'],
            'supplier_acceptance_title' => ['nullable', 'string', 'max:120'],
            'contract_number' => ['nullable', 'string', 'max:80'],
            'contract_date' => ['nullable', 'date'],
            'terms' => ['nullable', 'string'],
        ]);
        $purchaseOrder->update($data);
        return response()->json(['success' => true, 'message' => 'PO berhasil diperbarui.', 'data' => $this->formatPo($purchaseOrder->fresh(['purchaseRequest:id,pr_number', 'vendor:id,code,name', 'lines']))]);
    }

    public function cancelPurchaseOrder(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->ensureRelatedScope($request, PurchaseOrder::class, $purchaseOrder->id, 'created_by', 'purchaseRequest');
        if (! in_array($purchaseOrder->status, ['draft', 'approved'], true)) throw ValidationException::withMessages(['status' => 'PO tidak dapat dibatalkan dari status saat ini.']);
        if ($purchaseOrder->goodsReceipts()->exists() || $purchaseOrder->supplierInvoices()->exists()) throw ValidationException::withMessages(['status' => 'PO yang sudah memiliki GRN atau invoice tidak dapat dibatalkan.']);
        $purchaseOrder->update(['status' => 'cancelled']);
        return response()->json(['success' => true, 'message' => 'PO berhasil dibatalkan.', 'data' => $this->formatPo($purchaseOrder->fresh(['purchaseRequest:id,pr_number', 'vendor:id,code,name', 'lines']))]);
    }

    public function createPoFromPr(PurchaseRequest $purchaseRequest, Request $request): JsonResponse
    {
        if ($purchaseRequest->status !== 'approved') {
            throw ValidationException::withMessages(['status' => 'PO hanya dapat dibuat dari PR approved.']);
        }

        if ((float) $purchaseRequest->total_amount > 10000000) {
            throw ValidationException::withMessages(['status' => 'PR di atas Rp10.000.000 wajib melalui RFQ, quotation, dan CBA sebelum PO/Contract.']);
        }

        $data = $request->validate([
            'po_number' => ['nullable', 'string', 'max:80', 'unique:purchase_orders,po_number'],
            'po_date' => ['required', 'date'],
            'required_delivery_date' => ['nullable', 'date', 'after_or_equal:po_date'],
            'delivery_method' => ['nullable', 'string', 'max:30'],
            'payment_terms' => ['nullable', 'string', 'max:120'],
            'delivery_address' => ['nullable', 'string'],
            'delivery_contact_name' => ['nullable', 'string', 'max:120'],
            'delivery_contact_email' => ['nullable', 'email', 'max:160'],
            'supplier_company_name' => ['nullable', 'string', 'max:180'],
            'supplier_contact_name' => ['nullable', 'string', 'max:120'],
            'supplier_email' => ['nullable', 'email', 'max:160'],
            'supplier_phone' => ['nullable', 'string', 'max:50'],
            'supplier_fax' => ['nullable', 'string', 'max:50'],
            'supplier_mobile' => ['nullable', 'string', 'max:50'],
            'supplier_address' => ['nullable', 'string'],
            'procurement_contact_name' => ['nullable', 'string', 'max:120'],
            'procurement_contact_email' => ['nullable', 'email', 'max:160'],
            'procurement_address' => ['nullable', 'string'],
            'other_tax' => ['nullable', 'numeric', 'min:0'],
            'other_charges' => ['nullable', 'numeric', 'min:0'],
            'checked_by' => ['nullable', 'string', 'max:120'],
            'checked_by_title' => ['nullable', 'string', 'max:120'],
            'budget_holder_name' => ['nullable', 'string', 'max:120'],
            'budget_holder_title' => ['nullable', 'string', 'max:120'],
            'supplier_acceptance_name' => ['nullable', 'string', 'max:120'],
            'supplier_acceptance_title' => ['nullable', 'string', 'max:120'],
            'terms' => ['nullable', 'string'],
        ]);

        $purchaseRequest->loadMissing([
            'vendor:id,code,name,contact_person,contact_person_title,email,phone,address',
            'lines.budgetLine:id,line_code,description,currency_id',
            'lines.budgetLine.currency:id,code,name',
        ]);

        $po = DB::transaction(function () use ($purchaseRequest, $data) {
            $vendor = $purchaseRequest->vendor;
            $po = PurchaseOrder::create([
                'purchase_request_id' => $purchaseRequest->id,
                'vendor_id' => $purchaseRequest->vendor_id,
                'po_number' => $data['po_number'] ?? ('PO-'.now()->format('YmdHis').'-'.random_int(100, 999)),
                'po_date' => $data['po_date'],
                ...collect($data)->except(['po_number', 'po_date'])->all(),
                'required_delivery_date' => $data['required_delivery_date'] ?? $purchaseRequest->required_date,
                'delivery_method' => $data['delivery_method'] ?? $purchaseRequest->delivery_method,
                'delivery_address' => $data['delivery_address'] ?? $purchaseRequest->destination,
                'supplier_company_name' => $data['supplier_company_name'] ?? $vendor?->name,
                'supplier_contact_name' => $data['supplier_contact_name'] ?? $vendor?->contact_person,
                'supplier_email' => $data['supplier_email'] ?? $vendor?->email,
                'supplier_phone' => $data['supplier_phone'] ?? $vendor?->phone,
                'supplier_address' => $data['supplier_address'] ?? $vendor?->address,
                'terms' => $data['terms'] ?? null,
                'status' => 'draft',
                'created_by' => request()->user()->id,
            ]);

            foreach ($purchaseRequest->lines as $line) {
                $po->lines()->create([
                    'purchase_request_line_id' => $line->id,
                    'budget_line_id' => $line->budget_line_id,
                    'budget_code' => $line->budget_code ?: $line->budgetLine?->line_code,
                    'budget_line' => $line->budgetLine?->description,
                    'pr_line_item_no' => $line->line_order,
                    'item_description' => $line->item_description,
                    'unit' => $line->unit,
                    'quantity' => $line->quantity,
                    'currency' => $line->currency ?: ($line->budgetLine?->currency?->code ?: 'IDR'),
                    'unit_price' => $line->unit_price,
                    'total_amount' => $line->total_amount,
                    'line_order' => $line->line_order,
                ]);
            }

            return $po->load(['purchaseRequest:id,pr_number', 'vendor:id,code,name', 'lines']);
        });

        return response()->json(['success' => true, 'message' => 'PO berhasil dibuat dari PR.', 'data' => $this->formatPo($po)], Response::HTTP_CREATED);
    }

    public function submitPo(Request $request, PurchaseOrder $purchaseOrder, ApprovalWorkflowService $workflow): JsonResponse
    {
        if ($purchaseOrder->status !== 'draft') throw ValidationException::withMessages(['status' => 'Hanya PO draft yang dapat diajukan approval.']);
        $run = $workflow->start('po', $purchaseOrder, (float) $purchaseOrder->total_amount, $request->user()->id);
        return response()->json(['success' => true, 'workflow_managed' => (bool) $run, 'message' => $run ? 'PO diajukan ke Approval Matrix.' : 'Tidak ada Approval Matrix PO aktif; PO siap diapprove.', 'data' => $this->formatPo($purchaseOrder->fresh(['purchaseRequest:id,pr_number', 'vendor:id,code,name', 'lines']))]);
    }

    public function approvePo(Request $request, PurchaseOrder $purchaseOrder, ApprovalWorkflowService $workflow): JsonResponse
    {
        if ($purchaseOrder->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Hanya PO draft yang dapat diapprove.']);
        }

        $approval = $workflow->approve('po', $purchaseOrder, $request->user(), $request->input('notes'));
        if (! $approval['managed'] && $workflow->requiresApproval('po', $purchaseOrder, (float) $purchaseOrder->total_amount)) throw ValidationException::withMessages(['approval' => 'PO harus disubmit ke Approval Matrix sebelum approval.']);
        if ($approval['managed'] && ! $approval['completed']) return response()->json(['success' => true, 'message' => "Approval PO tahap selesai. Menunggu approver level {$approval['next_level']}.", 'data' => $this->formatPo($purchaseOrder->fresh(['purchaseRequest:id,pr_number', 'vendor:id,code,name', 'lines']))]);

        $purchaseOrder->update([
            'status' => 'approved',
            'approved_by' => request()->user()->id,
            'approved_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'PO berhasil diapprove.', 'data' => $this->formatPo($purchaseOrder->fresh(['purchaseRequest:id,pr_number', 'vendor:id,code,name', 'lines']))]);
    }

    public function createGrn(PurchaseOrder $purchaseOrder, Request $request): JsonResponse
    {
        $this->ensureRelatedScope($request, PurchaseOrder::class, $purchaseOrder->id, 'created_by', 'purchaseRequest');
        if ($purchaseOrder->status !== 'approved') {
            throw ValidationException::withMessages(['status' => 'GRN hanya dapat dibuat dari PO approved.']);
        }

        $data = $request->validate([
            'grn_number' => ['nullable', 'string', 'max:80', 'unique:goods_receipts,grn_number'],
            'receipt_date' => ['required', 'date'],
            'supplier_waybill_number' => ['nullable', 'string', 'max:100'],
            'receiving_location' => ['nullable', 'string', 'max:200'],
            'donor_name' => ['nullable', 'string', 'max:200'],
            'acceptable_for_payment' => ['nullable', 'boolean'],
            'remarks' => ['nullable', 'string'],
            'receipt_by_name' => ['nullable', 'string', 'max:120'],
            'receipt_by_position' => ['nullable', 'string', 'max:120'],
            'requester_name' => ['nullable', 'string', 'max:120'],
            'requester_position' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_line_id' => ['required', 'integer', 'exists:purchase_order_lines,id'],
            'lines.*.received_quantity' => ['required', 'numeric', 'min:0.01'],
            'lines.*.quality_check' => ['nullable', 'string', 'max:50'],
            'lines.*.comment' => ['nullable', 'string'],
        ]);
        $this->validateOrderLines($purchaseOrder, $data['lines']);

        $grn = DB::transaction(function () use ($purchaseOrder, $data) {
            $grn = GoodsReceipt::create([
                'purchase_order_id' => $purchaseOrder->id,
                'grn_number' => $data['grn_number'] ?? ('GRN-'.now()->format('YmdHis').'-'.random_int(100, 999)),
                'receipt_date' => $data['receipt_date'],
                'supplier_waybill_number' => $data['supplier_waybill_number'] ?? null,
                'receiving_location' => $data['receiving_location'] ?? null,
                'donor_name' => $data['donor_name'] ?? null,
                'acceptable_for_payment' => $data['acceptable_for_payment'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'receipt_by_name' => $data['receipt_by_name'] ?? null,
                'receipt_by_position' => $data['receipt_by_position'] ?? null,
                'requester_name' => $data['requester_name'] ?? null,
                'requester_position' => $data['requester_position'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'received',
                'received_by' => request()->user()->id,
                'received_at' => now(),
                'created_by' => request()->user()->id,
            ]);

            foreach ($data['lines'] as $line) {
                $grn->lines()->create($line);
            }

            return $grn->load($this->grnRelations());
        });

        return response()->json(['success' => true, 'message' => 'GRN berhasil dibuat.', 'data' => $this->formatGrn($grn)], Response::HTTP_CREATED);
    }

    public function createInvoice(PurchaseOrder $purchaseOrder, Request $request): JsonResponse
    {
        $this->ensureRelatedScope($request, PurchaseOrder::class, $purchaseOrder->id, 'created_by', 'purchaseRequest');
        if ($purchaseOrder->status !== 'approved') {
            throw ValidationException::withMessages(['status' => 'Supplier invoice hanya dapat dibuat dari PO approved.']);
        }

        $data = $request->validate([
            'goods_receipt_id' => ['nullable', 'integer', 'exists:goods_receipts,id'],
            'invoice_number' => ['nullable', 'string', 'max:80'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'tax_id' => ['nullable', 'integer', 'exists:taxes,id'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_line_id' => ['required', 'integer', 'exists:purchase_order_lines,id'],
            'lines.*.item_description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0.01'],
        ]);
        $this->validateOrderLines($purchaseOrder, $data['lines']);
        if (! empty($data['goods_receipt_id']) && ! $purchaseOrder->goodsReceipts()->whereKey($data['goods_receipt_id'])->exists()) {
            throw ValidationException::withMessages(['goods_receipt_id' => 'Goods receipt harus berasal dari PO yang sama.']);
        }

        $invoice = DB::transaction(function () use ($purchaseOrder, $data) {
            $lines = $data['lines'];
            unset($data['lines']);
            $invoice = SupplierInvoice::create([
                ...$data,
                'purchase_order_id' => $purchaseOrder->id,
                'vendor_id' => $purchaseOrder->vendor_id,
                'status' => 'draft',
                'created_by' => request()->user()->id,
                'match_status' => 'unchecked',
                'total_amount' => 0,
            ]);

            $total = 0;
            foreach ($lines as $line) {
                $line['total_amount'] = round((float) $line['quantity'] * (float) $line['unit_price'], 2);
                $total += $line['total_amount'];
                $invoice->lines()->create($line);
            }
            $invoice->update(['total_amount' => $total]);

            return $invoice->load(['purchaseOrder:id,po_number', 'goodsReceipt:id,grn_number', 'vendor:id,code,name,npwp', 'tax:id,code,name,tax_type,rate_percent', 'lines']);
        });

        return response()->json(['success' => true, 'message' => 'Supplier invoice berhasil dibuat.', 'data' => $this->formatInvoice($invoice)], Response::HTTP_CREATED);
    }

    public function createDirectInvoice(PurchaseRequest $purchaseRequest, Request $request): JsonResponse
    {
        if ($purchaseRequest->status !== 'approved') {
            throw ValidationException::withMessages(['status' => 'Direct invoice hanya dapat dibuat dari PR approved.']);
        }
        if ((float) $purchaseRequest->total_amount > 2000000) {
            throw ValidationException::withMessages(['total_amount' => 'Jalur direct hanya untuk nilai sampai Rp2.000.000.']);
        }

        $data = $request->validate([
            'invoice_number' => ['required', 'string', 'max:80', 'unique:supplier_invoices,invoice_number'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'payment_method' => ['required', 'in:cash,ebt'],
            'bank_account_id' => ['required', 'integer', 'exists:bank_accounts,id'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
        ]);

        $invoice = DB::transaction(function () use ($purchaseRequest, $request, $data) {
            $invoice = SupplierInvoice::create([
                'purchase_request_id' => $purchaseRequest->id,
                'vendor_id' => $purchaseRequest->vendor_id,
                'invoice_number' => 'DIR-'.$purchaseRequest->pr_number.'-'.now()->format('YmdHis').'-'.random_int(100, 999),
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'notes' => trim(($data['notes'] ?? '')."\nPayment method: ".strtoupper($data['payment_method'])),
                'status' => 'draft',
                'match_status' => 'unchecked',
                'total_amount' => $purchaseRequest->total_amount,
                'created_by' => $request->user()->id,
            ]);
            Payment::create([
                'supplier_invoice_id' => $invoice->id,
                'vendor_id' => $purchaseRequest->vendor_id,
                'bank_account_id' => $data['bank_account_id'],
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'payment_number' => 'DIR-PAY-'.now()->format('YmdHis').'-'.random_int(100, 999),
                'payment_date' => $data['invoice_date'],
                'amount' => $purchaseRequest->total_amount,
                'reference' => $invoice->invoice_number,
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ]);
            return $invoice;
        });

        return response()->json(['success' => true, 'message' => 'Direct receipt/invoice berhasil dibuat.', 'data' => $this->formatInvoice($invoice->load(['purchaseRequest:id,pr_number', 'vendor:id,code,name', 'lines']))], Response::HTTP_CREATED);
    }

    public function threeWayMatch(Request $request, SupplierInvoice $supplierInvoice): JsonResponse
    {
        $this->ensureRelatedScope($request, SupplierInvoice::class, $supplierInvoice->id, 'created_by', 'purchaseOrder.purchaseRequest');
        if ($supplierInvoice->status === 'posted' || $supplierInvoice->status === 'paid') {
            throw ValidationException::withMessages(['status' => 'Invoice yang sudah diposting atau dibayar tidak dapat di-match ulang.']);
        }
        $supplierInvoice->load(['purchaseOrder.lines', 'goodsReceipt.lines', 'lines']);

        $poTotal = round((float) $supplierInvoice->purchaseOrder?->lines->sum('total_amount'), 2);
        $grnTotal = 0;
        foreach ($supplierInvoice->goodsReceipt?->lines ?? [] as $line) {
            $poLine = $supplierInvoice->purchaseOrder?->lines->firstWhere('id', $line->purchase_order_line_id);
            $grnTotal += round((float) $line->received_quantity * (float) ($poLine?->unit_price ?? 0), 2);
        }
        $invoiceTotal = round((float) $supplierInvoice->lines->sum('total_amount'), 2);

        $matchStatus = 'mismatch';
        if ($supplierInvoice->goodsReceipt?->acceptable_for_payment !== false) {
            if ($poTotal === $grnTotal && $poTotal === $invoiceTotal) {
                $matchStatus = 'matched';
            } elseif ($grnTotal > 0 && $invoiceTotal <= $poTotal) {
                $matchStatus = 'partial_match';
            }
        }

        $supplierInvoice->update([
            'match_status' => $matchStatus,
            'status' => $matchStatus === 'matched' ? 'matched' : 'draft',
        ]);

        return response()->json([
            'success' => true,
            'message' => '3-way match selesai.',
            'data' => [
                'invoice_id' => $supplierInvoice->id,
                'po_total' => $poTotal,
                'grn_total' => round($grnTotal, 2),
                'invoice_total' => $invoiceTotal,
                'match_status' => $matchStatus,
            ],
        ]);
    }

    private function validateOrderLines(PurchaseOrder $purchaseOrder, array $lines, ?int $ignoreGoodsReceiptId = null): void
    {
        $lineIds = collect($lines)->pluck('purchase_order_line_id')->filter()->unique()->values();
        $validIds = $purchaseOrder->lines()->whereIn('id', $lineIds)->pluck('id');
        if ($validIds->count() !== $lineIds->count()) {
            throw ValidationException::withMessages(['lines' => 'Semua line harus berasal dari purchase order yang dipilih.']);
        }
        foreach ($lines as $line) {
            if (! isset($line['received_quantity'])) continue;
            $ordered = (float) $purchaseOrder->lines()->whereKey($line['purchase_order_line_id'])->value('quantity');
            $received = (float) $purchaseOrder->goodsReceipts()->where('status', '!=', 'cancelled')->when($ignoreGoodsReceiptId, fn ($q) => $q->where('id', '!=', $ignoreGoodsReceiptId))->with('lines')->get()->sum(fn ($grn) => (float) $grn->lines->where('purchase_order_line_id', $line['purchase_order_line_id'])->sum('received_quantity'));
            if ($received + (float) $line['received_quantity'] > $ordered) throw ValidationException::withMessages(['lines' => 'The received quantity exceeds the purchase order quantity.']);
        }
    }

    private function ensureRelatedScope(Request $request, string $model, int $id, string $ownerColumn, string $relationPath): void
    {
        $query = $model::query()->whereKey($id);
        app(DataScopeService::class)->applyRelatedProjectScope($query, $request->user(), $ownerColumn, $relationPath);
        if (! $query->exists()) abort(Response::HTTP_FORBIDDEN, 'Tidak boleh mengakses dokumen procurement ini.');
    }

    private function formatPo(PurchaseOrder $po): array
    {
        $po->loadMissing([
            'purchaseRequest:id,pr_number,project_id,justification,requester_name,requester_email,requester_phone,requester_address,donor_name,requester_program',
            'purchaseRequest.project:id,code,name,program_id,grant_agreement_id,relation_no',
            'purchaseRequest.project.program:id,code,name',
            'purchaseRequest.project.grantAgreement:id,grant_no,agreement_name,donor_id,relation_no',
            'purchaseRequest.project.grantAgreement.donor:id,code,name',
            'vendor:id,code,name,contact_person,contact_person_title,email,phone,address',
            'approvedBy:id,name,email',
            'lines.purchaseRequestLine:id,purchase_request_id,budget_line_id,account_code,budget_code,source_of_fund,unit,currency,line_order',
            'lines.budgetLine:id,line_code,description,gl_account_id,currency_id',
            'lines.budgetLine.glAccount:id,code,name',
            'lines.budgetLine.currency:id,code,name',
        ]);

        return [
            'id' => $po->id,
            'fiscal_year_id' => $po->fiscal_year_id,
            'fiscal_year' => $po->fiscalYear?->only(['id', 'year', 'name']),
            'po_number' => $po->po_number,
            'purchase_request_id' => $po->purchase_request_id,
            'pr_number' => $po->purchaseRequest?->pr_number,
            'project_id' => $po->purchaseRequest?->project_id,
            'project_name' => $po->purchaseRequest?->project?->name,
            'relation_no' => $po->purchaseRequest?->project?->relation_no,
            'program_name' => $po->purchaseRequest?->project?->program?->name ?: $po->purchaseRequest?->requester_program,
            'grant_name' => $po->purchaseRequest?->project?->grantAgreement?->agreement_name,
            'donor_name' => $po->purchaseRequest?->donor_name ?: $po->purchaseRequest?->project?->grantAgreement?->donor?->name,
            'vendor_id' => $po->vendor_id,
            'vendor_name' => $po->vendor?->name,
            'supplier_company_name' => $po->supplier_company_name ?: $po->vendor?->name,
            'po_date' => $po->po_date?->toDateString(),
            'required_delivery_date' => $po->required_delivery_date?->toDateString(),
            'delivery_method' => $po->delivery_method,
            'payment_terms' => $po->payment_terms,
            'delivery_address' => $po->delivery_address,
            'delivery_contact_name' => $po->delivery_contact_name,
            'delivery_contact_email' => $po->delivery_contact_email,
            'supplier_contact_name' => $po->supplier_contact_name,
            'supplier_email' => $po->supplier_email,
            'supplier_phone' => $po->supplier_phone,
            'supplier_fax' => $po->supplier_fax,
            'supplier_mobile' => $po->supplier_mobile,
            'supplier_address' => $po->supplier_address,
            'procurement_contact_name' => $po->procurement_contact_name,
            'procurement_contact_email' => $po->procurement_contact_email,
            'procurement_address' => $po->procurement_address,
            'other_tax' => $po->other_tax,
            'other_charges' => $po->other_charges,
            'checked_by' => $po->checked_by,
            'checked_by_title' => $po->checked_by_title,
            'budget_holder_name' => $po->budget_holder_name,
            'budget_holder_title' => $po->budget_holder_title,
            'supplier_acceptance_name' => $po->supplier_acceptance_name,
            'supplier_acceptance_title' => $po->supplier_acceptance_title,
            'terms' => $po->terms,
            'status' => $po->status,
            'approval_progress' => app(\App\Services\Approval\ApprovalWorkflowService::class)->progressFor('po', $po, request()->user()),
            'subtotal_amount' => $po->subtotal_amount,
            'total_amount' => $po->total_amount,
            'approved_by_name' => $po->approvedBy?->name,
            'approved_at' => $po->approved_at?->toISOString(),
            'lines' => $po->lines->map(fn ($line) => [
                'id' => $line->id,
                'purchase_request_line_id' => $line->purchase_request_line_id,
                'budget_line_id' => $line->budget_line_id,
                'budget_code' => $line->budget_code ?: ($line->purchaseRequestLine?->budget_code ?: $line->budgetLine?->line_code),
                'budget_line' => $line->budget_line ?: $line->budgetLine?->description,
                'account_code' => $line->purchaseRequestLine?->account_code ?: $line->budgetLine?->glAccount?->code,
                'source_of_fund' => $line->purchaseRequestLine?->source_of_fund,
                'pr_number' => $po->purchaseRequest?->pr_number,
                'pr_line_item_no' => $line->pr_line_item_no ?: ($line->purchaseRequestLine?->line_order ?: $line->line_order),
                'item_description' => $line->item_description,
                'unit' => $line->unit ?: $line->purchaseRequestLine?->unit,
                'quantity' => $line->quantity,
                'currency' => $line->currency ?: ($line->purchaseRequestLine?->currency ?: $line->budgetLine?->currency?->code),
                'unit_price' => $line->unit_price,
                'total_amount' => $line->total_amount,
                'line_order' => $line->line_order,
            ])->values(),
        ];
    }

    private function formatGrn(GoodsReceipt $grn): array
    {
        $grn->loadMissing($this->grnRelations());
        $po = $grn->purchaseOrder;
        $pr = $po?->purchaseRequest;
        $vendor = $po?->vendor;
        $lines = $grn->lines->map(function ($line) use ($po, $pr) {
            $poLine = $line->purchaseOrderLine;
            $receivedQuantity = (float) $line->received_quantity;
            $unitPrice = (float) ($poLine?->unit_price ?? 0);
            return [
                'id' => $line->id,
                'purchase_order_line_id' => $line->purchase_order_line_id,
                'pr_number' => $pr?->pr_number,
                'line_item' => $poLine?->pr_line_item_no ?: $poLine?->line_order,
                'item_description' => $poLine?->item_description,
                'unit' => $poLine?->unit,
                'quantity_expected' => $poLine?->quantity,
                'received_quantity' => $line->received_quantity,
                'quality_check' => $line->quality_check,
                'comment' => $line->comment,
                'unit_price' => $poLine?->unit_price,
                'total_amount' => round($receivedQuantity * $unitPrice, 2),
            ];
        })->values();

        return [
            'id' => $grn->id,
            'fiscal_year_id' => $grn->fiscal_year_id,
            'fiscal_year' => $grn->fiscalYear?->only(['id', 'year', 'name']),
            'grn_number' => $grn->grn_number,
            'purchase_order_id' => $grn->purchase_order_id,
            'po_number' => $po?->po_number,
            'purchase_order' => $po ? ['id' => $po->id, 'po_number' => $po->po_number, 'pr_number' => $pr?->pr_number, 'vendor_name' => $vendor?->name] : null,
            'pr_number' => $pr?->pr_number,
            'purchase_request' => $pr ? ['id' => $pr->id, 'pr_number' => $pr->pr_number, 'project_name' => $pr->project?->name, 'donor_name' => $pr->donor_name] : null,
            'vendor_name' => $vendor?->name,
            'supplier_name' => $vendor?->name,
            'received_from' => $vendor?->name ?: $grn->donor_name,
            'receipt_date' => $grn->receipt_date?->toDateString(),
            'grn_date' => $grn->receipt_date?->toDateString(),
            'supplier_waybill_number' => $grn->supplier_waybill_number,
            'receiving_location' => $grn->receiving_location,
            'donor_name' => $grn->donor_name,
            'acceptable_for_payment' => $grn->acceptable_for_payment,
            'remarks' => $grn->remarks,
            'receipt_by_name' => $grn->receipt_by_name,
            'receipt_by_position' => $grn->receipt_by_position,
            'requester_name' => $grn->requester_name,
            'requester_position' => $grn->requester_position,
            'notes' => $grn->notes,
            'status' => $grn->status,
            'total_amount' => $lines->sum('total_amount'),
            'lines' => $lines,
        ];
    }

    private function grnRelations(): array
    {
        return [
            'fiscalYear:id,year,name',
            'purchaseOrder:id,purchase_request_id,vendor_id,po_number',
            'purchaseOrder.purchaseRequest:id,pr_number,project_id,donor_name',
            'purchaseOrder.purchaseRequest.project:id,code,name',
            'purchaseOrder.vendor:id,code,name',
            'lines.purchaseOrderLine:id,purchase_order_id,item_description,unit,quantity,unit_price,pr_line_item_no,line_order',
        ];
    }

    private function formatInvoice(SupplierInvoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'fiscal_year_id' => $invoice->fiscal_year_id,
            'fiscal_year' => $invoice->fiscalYear?->only(['id', 'year', 'name']),
            'invoice_number' => $invoice->invoice_number,
            'po_number' => $invoice->purchaseOrder?->po_number,
            'grn_number' => $invoice->goodsReceipt?->grn_number,
            'vendor_name' => $invoice->vendor?->name,
            'tax' => $invoice->tax ? ['id' => $invoice->tax->id, 'code' => $invoice->tax->code, 'name' => $invoice->tax->name, 'tax_type' => $invoice->tax->tax_type, 'rate_percent' => $invoice->tax->rate_percent] : null,
            'invoice_date' => $invoice->invoice_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'status' => $invoice->status,
            'approval_progress' => app(\App\Services\Approval\ApprovalWorkflowService::class)->progressFor('ap', $invoice, request()->user()),
            'match_status' => $invoice->match_status,
            'total_amount' => $invoice->total_amount,
            'lines' => $invoice->lines,
        ];
    }
}
