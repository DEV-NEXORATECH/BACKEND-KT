<?php

namespace App\Http\Controllers\Api\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Procurement\GoodsReceipt;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseRequest;
use App\Models\Procurement\SupplierInvoice;
use App\Services\Rbac\DataScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ProcurementFulfillmentController extends Controller
{
    public function processOptions(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => [
            ['value' => 'pr', 'title' => 'Purchase Request', 'subtitle' => 'Ajukan dan pantau permintaan pembelian.', 'endpoint' => '/v1/procurement/purchase-requests', 'columns' => [['key' => 'pr_number', 'label' => 'PR Number'], ['key' => 'request_date', 'label' => 'Date'], ['key' => 'status', 'label' => 'Status'], ['key' => 'total_amount', 'label' => 'Amount']]],
            ['value' => 'po', 'title' => 'Purchase Orders', 'subtitle' => 'Kelola PO yang dibuat dari PR approved.', 'endpoint' => '/v1/procurement/purchase-orders', 'columns' => [['key' => 'po_number', 'label' => 'PO Number'], ['key' => 'po_date', 'label' => 'Date'], ['key' => 'vendor', 'label' => 'Vendor'], ['key' => 'status', 'label' => 'Status']]],
            ['value' => 'grn', 'title' => 'Goods Receipts', 'subtitle' => 'Catat penerimaan barang berdasarkan PO.', 'endpoint' => '/v1/procurement/goods-receipts', 'columns' => [['key' => 'grn_number', 'label' => 'GRN Number'], ['key' => 'receipt_date', 'label' => 'Date'], ['key' => 'purchase_order', 'label' => 'Purchase Order'], ['key' => 'status', 'label' => 'Status']]],
            ['value' => 'invoice', 'title' => 'Supplier Invoices', 'subtitle' => 'Kelola invoice supplier dan 3-way match.', 'endpoint' => '/v1/procurement/supplier-invoices', 'columns' => [['key' => 'invoice_number', 'label' => 'Invoice Number'], ['key' => 'invoice_date', 'label' => 'Date'], ['key' => 'vendor', 'label' => 'Vendor'], ['key' => 'match_status', 'label' => 'Match Status'], ['key' => 'status', 'label' => 'Status']]],
        ]]);
    }

    public function goodsReceipts(Request $request): JsonResponse
    {
        $query = GoodsReceipt::with('purchaseOrder:id,po_number');
        app(DataScopeService::class)->applyScope($query, $request->user(), 'created_by', 'purchaseOrder.project_id', null, ['procurement.grn.view']);
        return response()->json(['success' => true, 'data' => $query->latest('id')->get()->map(fn ($grn) => ['id' => $grn->id, 'grn_number' => $grn->grn_number, 'receipt_date' => $grn->receipt_date?->toDateString(), 'purchase_order' => $grn->purchaseOrder?->po_number, 'status' => $grn->status])]);
    }

    public function showGoodsReceipt(GoodsReceipt $goodsReceipt): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->formatGrn($goodsReceipt->load(['purchaseOrder:id,po_number', 'lines.purchaseOrderLine']))]);
    }

    public function updateGoodsReceipt(Request $request, GoodsReceipt $goodsReceipt): JsonResponse
    {
        if ($goodsReceipt->status === 'cancelled') throw ValidationException::withMessages(['status' => 'GRN sudah dibatalkan.']);
        $data = $request->validate(['receipt_date' => ['sometimes', 'date'], 'notes' => ['nullable', 'string'], 'lines' => ['sometimes', 'array', 'min:1'], 'lines.*.purchase_order_line_id' => ['required_with:lines', 'integer', 'exists:purchase_order_lines,id'], 'lines.*.received_quantity' => ['required_with:lines', 'numeric', 'min:0.01']]);
        if (isset($data['lines'])) {
            $this->validateOrderLines($goodsReceipt->purchaseOrder, $data['lines'], $goodsReceipt->id);
            $goodsReceipt->lines()->delete();
            foreach ($data['lines'] as $line) $goodsReceipt->lines()->create($line);
            unset($data['lines']);
        }
        $goodsReceipt->update($data);
        return response()->json(['success' => true, 'message' => 'GRN berhasil diperbarui.', 'data' => $this->formatGrn($goodsReceipt->fresh(['purchaseOrder:id,po_number', 'lines.purchaseOrderLine']))]);
    }

    public function cancelGoodsReceipt(GoodsReceipt $goodsReceipt): JsonResponse
    {
        if ($goodsReceipt->status === 'cancelled') throw ValidationException::withMessages(['status' => 'GRN sudah dibatalkan.']);
        if (SupplierInvoice::query()->where('goods_receipt_id', $goodsReceipt->id)->whereNotIn('status', ['cancelled'])->exists()) throw ValidationException::withMessages(['status' => 'GRN yang sudah direferensikan invoice tidak dapat dibatalkan.']);
        $goodsReceipt->update(['status' => 'cancelled']);
        return response()->json(['success' => true, 'message' => 'GRN berhasil dibatalkan.', 'data' => $this->formatGrn($goodsReceipt->fresh(['purchaseOrder:id,po_number', 'lines.purchaseOrderLine']))]);
    }

    public function supplierInvoices(Request $request): JsonResponse
    {
        $query = SupplierInvoice::with(['purchaseOrder:id,po_number', 'vendor:id,code,name']);
        app(DataScopeService::class)->applyScope($query, $request->user(), 'created_by', 'purchaseOrder.project_id', null, ['procurement.invoice.view', 'ap.view']);
        return response()->json(['success' => true, 'data' => $query->latest('id')->get()->map(fn ($invoice) => ['id' => $invoice->id, 'invoice_number' => $invoice->invoice_number, 'invoice_date' => $invoice->invoice_date?->toDateString(), 'purchase_order' => $invoice->purchaseOrder?->po_number, 'vendor' => $invoice->vendor?->name, 'match_status' => $invoice->match_status, 'status' => $invoice->status, 'total_amount' => $invoice->total_amount])]);
    }

    public function showSupplierInvoice(SupplierInvoice $supplierInvoice): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->formatInvoice($supplierInvoice->load(['purchaseOrder:id,po_number', 'goodsReceipt:id,grn_number', 'vendor:id,code,name', 'lines']))]);
    }

    public function updateSupplierInvoice(Request $request, SupplierInvoice $supplierInvoice): JsonResponse
    {
        if (! in_array($supplierInvoice->status, ['draft', 'matched'], true)) throw ValidationException::withMessages(['status' => 'Invoice hanya dapat diubah saat draft atau matched.']);
        $data = $request->validate(['invoice_date' => ['sometimes', 'date'], 'due_date' => ['nullable', 'date'], 'notes' => ['nullable', 'string'], 'lines' => ['sometimes', 'array', 'min:1'], 'lines.*.purchase_order_line_id' => ['required_with:lines', 'integer', 'exists:purchase_order_lines,id'], 'lines.*.item_description' => ['required_with:lines', 'string', 'max:255'], 'lines.*.quantity' => ['required_with:lines', 'numeric', 'min:0.01'], 'lines.*.unit_price' => ['required_with:lines', 'numeric', 'min:0.01']]);
        if (isset($data['lines'])) {
            $this->validateOrderLines($supplierInvoice->purchaseOrder, $data['lines']);
            $supplierInvoice->lines()->delete();
            $total = 0;
            foreach ($data['lines'] as $line) { $line['total_amount'] = round((float) $line['quantity'] * (float) $line['unit_price'], 2); $total += $line['total_amount']; $supplierInvoice->lines()->create($line); }
            $data['total_amount'] = $total;
            $supplierInvoice->update(['match_status' => 'unchecked', 'status' => 'draft']);
            unset($data['lines']);
        }
        $supplierInvoice->update($data);
        return response()->json(['success' => true, 'message' => 'Supplier invoice berhasil diperbarui.', 'data' => $this->formatInvoice($supplierInvoice->fresh(['purchaseOrder:id,po_number', 'goodsReceipt:id,grn_number', 'vendor:id,code,name', 'lines']))]);
    }

    public function cancelSupplierInvoice(SupplierInvoice $supplierInvoice): JsonResponse
    {
        if (in_array($supplierInvoice->status, ['posted', 'paid', 'cancelled'], true)) throw ValidationException::withMessages(['status' => 'Invoice tidak dapat dibatalkan dari status saat ini.']);
        $supplierInvoice->update(['status' => 'cancelled']);
        return response()->json(['success' => true, 'message' => 'Supplier invoice berhasil dibatalkan.', 'data' => $this->formatInvoice($supplierInvoice->fresh(['purchaseOrder:id,po_number', 'goodsReceipt:id,grn_number', 'vendor:id,code,name', 'lines']))]);
    }

    public function purchaseOrders(Request $request): JsonResponse
    {
        $query = PurchaseOrder::with(['purchaseRequest:id,pr_number', 'vendor:id,code,name', 'lines']);
        app(DataScopeService::class)->applyScope($query, $request->user(), 'created_by', 'purchaseRequest.project_id', null, ['procurement.po.view']);
        return response()->json([
            'success' => true,
            'data' => $query->latest('id')->get()->map(fn ($po) => $this->formatPo($po)),
        ]);
    }

    public function showPurchaseOrder(PurchaseOrder $purchaseOrder): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->formatPo($purchaseOrder->load(['purchaseRequest:id,pr_number', 'vendor:id,code,name', 'lines']))]);
    }

    public function updatePurchaseOrder(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        if ($purchaseOrder->status !== 'draft') throw ValidationException::withMessages(['status' => 'PO hanya dapat diubah saat draft.']);
        $data = $request->validate(['po_date' => ['sometimes', 'date'], 'contract_number' => ['nullable', 'string', 'max:80'], 'contract_date' => ['nullable', 'date'], 'terms' => ['nullable', 'string']]);
        $purchaseOrder->update($data);
        return response()->json(['success' => true, 'message' => 'PO berhasil diperbarui.', 'data' => $this->formatPo($purchaseOrder->fresh(['purchaseRequest:id,pr_number', 'vendor:id,code,name', 'lines']))]);
    }

    public function cancelPurchaseOrder(PurchaseOrder $purchaseOrder): JsonResponse
    {
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

        $data = $request->validate([
            'po_date' => ['required', 'date'],
            'terms' => ['nullable', 'string'],
        ]);

        $po = DB::transaction(function () use ($purchaseRequest, $data) {
            $po = PurchaseOrder::create([
                'purchase_request_id' => $purchaseRequest->id,
                'vendor_id' => $purchaseRequest->vendor_id,
                'po_number' => 'PO-'.now()->format('YmdHis').'-'.random_int(100, 999),
                'po_date' => $data['po_date'],
                'terms' => $data['terms'] ?? null,
                'status' => 'draft',
            ]);

            foreach ($purchaseRequest->lines as $line) {
                $po->lines()->create([
                    'purchase_request_line_id' => $line->id,
                    'budget_line_id' => $line->budget_line_id,
                    'item_description' => $line->item_description,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price,
                    'total_amount' => $line->total_amount,
                    'line_order' => $line->line_order,
                ]);
            }

            return $po->load(['purchaseRequest:id,pr_number', 'vendor:id,code,name', 'lines']);
        });

        return response()->json(['success' => true, 'message' => 'PO berhasil dibuat dari PR.', 'data' => $this->formatPo($po)], Response::HTTP_CREATED);
    }

    public function approvePo(PurchaseOrder $purchaseOrder): JsonResponse
    {
        if ($purchaseOrder->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Hanya PO draft yang dapat diapprove.']);
        }

        $purchaseOrder->update([
            'status' => 'approved',
            'approved_by' => request()->user()->id,
            'approved_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'PO berhasil diapprove.', 'data' => $this->formatPo($purchaseOrder->fresh(['purchaseRequest:id,pr_number', 'vendor:id,code,name', 'lines']))]);
    }

    public function createGrn(PurchaseOrder $purchaseOrder, Request $request): JsonResponse
    {
        if ($purchaseOrder->status !== 'approved') {
            throw ValidationException::withMessages(['status' => 'GRN hanya dapat dibuat dari PO approved.']);
        }

        $data = $request->validate([
            'receipt_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_line_id' => ['required', 'integer', 'exists:purchase_order_lines,id'],
            'lines.*.received_quantity' => ['required', 'numeric', 'min:0.01'],
        ]);
        $this->validateOrderLines($purchaseOrder, $data['lines']);

        $grn = DB::transaction(function () use ($purchaseOrder, $data) {
            $grn = GoodsReceipt::create([
                'purchase_order_id' => $purchaseOrder->id,
                'grn_number' => 'GRN-'.now()->format('YmdHis').'-'.random_int(100, 999),
                'receipt_date' => $data['receipt_date'],
                'notes' => $data['notes'] ?? null,
                'status' => 'received',
                'received_by' => request()->user()->id,
                'received_at' => now(),
            ]);

            foreach ($data['lines'] as $line) {
                $grn->lines()->create($line);
            }

            return $grn->load(['purchaseOrder:id,po_number', 'lines.purchaseOrderLine']);
        });

        return response()->json(['success' => true, 'message' => 'GRN berhasil dibuat.', 'data' => $this->formatGrn($grn)], Response::HTTP_CREATED);
    }

    public function createInvoice(PurchaseOrder $purchaseOrder, Request $request): JsonResponse
    {
        if ($purchaseOrder->status !== 'approved') {
            throw ValidationException::withMessages(['status' => 'Supplier invoice hanya dapat dibuat dari PO approved.']);
        }

        $data = $request->validate([
            'goods_receipt_id' => ['nullable', 'integer', 'exists:goods_receipts,id'],
            'invoice_number' => ['required', 'string', 'max:80', 'unique:supplier_invoices,invoice_number'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
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

            return $invoice->load(['purchaseOrder:id,po_number', 'goodsReceipt:id,grn_number', 'vendor:id,code,name', 'lines']);
        });

        return response()->json(['success' => true, 'message' => 'Supplier invoice berhasil dibuat.', 'data' => $this->formatInvoice($invoice)], Response::HTTP_CREATED);
    }

    public function threeWayMatch(SupplierInvoice $supplierInvoice): JsonResponse
    {
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
        if ($poTotal === $grnTotal && $poTotal === $invoiceTotal) {
            $matchStatus = 'matched';
        } elseif ($grnTotal > 0 && $invoiceTotal <= $poTotal) {
            $matchStatus = 'partial_match';
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
            if ($received + (float) $line['received_quantity'] > $ordered) throw ValidationException::withMessages(['lines' => 'Jumlah penerimaan melebihi quantity PO.']);
        }
    }

    private function formatPo(PurchaseOrder $po): array
    {
        return [
            'id' => $po->id,
            'po_number' => $po->po_number,
            'purchase_request_id' => $po->purchase_request_id,
            'pr_number' => $po->purchaseRequest?->pr_number,
            'vendor_id' => $po->vendor_id,
            'vendor_name' => $po->vendor?->name,
            'po_date' => $po->po_date?->toDateString(),
            'status' => $po->status,
            'total_amount' => $po->total_amount,
            'lines' => $po->lines,
        ];
    }

    private function formatGrn(GoodsReceipt $grn): array
    {
        return [
            'id' => $grn->id,
            'grn_number' => $grn->grn_number,
            'po_number' => $grn->purchaseOrder?->po_number,
            'receipt_date' => $grn->receipt_date?->toDateString(),
            'status' => $grn->status,
            'lines' => $grn->lines,
        ];
    }

    private function formatInvoice(SupplierInvoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'po_number' => $invoice->purchaseOrder?->po_number,
            'grn_number' => $invoice->goodsReceipt?->grn_number,
            'vendor_name' => $invoice->vendor?->name,
            'invoice_date' => $invoice->invoice_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'status' => $invoice->status,
            'match_status' => $invoice->match_status,
            'total_amount' => $invoice->total_amount,
            'lines' => $invoice->lines,
        ];
    }
}
