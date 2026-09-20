<?php

namespace App\Http\Controllers\Api\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Procurement\GoodsReceipt;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseRequest;
use App\Models\Procurement\SupplierInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ProcurementFulfillmentController extends Controller
{
    public function purchaseOrders(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => PurchaseOrder::with(['purchaseRequest:id,pr_number', 'vendor:id,code,name', 'lines'])->latest('id')->get()->map(fn ($po) => $this->formatPo($po)),
        ]);
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

    private function formatPo(PurchaseOrder $po): array
    {
        return [
            'id' => $po->id,
            'po_number' => $po->po_number,
            'purchase_request_id' => $po->purchase_request_id,
            'pr_number' => $po->purchaseRequest?->pr_number,
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
