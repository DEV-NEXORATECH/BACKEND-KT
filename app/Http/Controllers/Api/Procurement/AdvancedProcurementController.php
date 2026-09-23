<?php

namespace App\Http\Controllers\Api\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Procurement\ComparativeBidAnalysis;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseRequest;
use App\Models\Procurement\Rfq;
use App\Models\Procurement\VendorQuotation;
use App\Services\Rbac\DataScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AdvancedProcurementController extends Controller
{
    public function rfqs(Request $request): JsonResponse
    {
        $query = Rfq::with(['purchaseRequest:id,pr_number,status', 'vendors:id,code,name', 'quotations.vendor:id,code,name', 'cba.selectedVendor:id,code,name']);
        app(DataScopeService::class)->applyScope($query, $request->user(), 'created_by', 'purchaseRequest.project_id', null, ['procurement.rfq.view']);
        return response()->json(['success' => true, 'data' => $query->latest('id')->get()->map(fn (Rfq $rfq) => $this->formatRfq($rfq))]);
    }

    public function createRfq(PurchaseRequest $purchaseRequest, Request $request): JsonResponse
    {
        if ($purchaseRequest->status !== 'approved') {
            throw ValidationException::withMessages(['status' => 'RFQ hanya dapat dibuat dari PR approved.']);
        }

        $data = $request->validate([
            'rfq_date' => ['required', 'date'],
            'submission_deadline' => ['nullable', 'date'],
            'terms' => ['nullable', 'string'],
            'vendor_ids' => ['required', 'array', 'min:1'],
            'vendor_ids.*' => ['integer', 'exists:vendors,id'],
        ]);

        $rfq = DB::transaction(function () use ($purchaseRequest, $data) {
            $rfq = Rfq::create([
                'purchase_request_id' => $purchaseRequest->id,
                'rfq_number' => 'RFQ-'.now()->format('YmdHis').'-'.random_int(100, 999),
                'rfq_date' => $data['rfq_date'],
                'submission_deadline' => $data['submission_deadline'] ?? null,
                'terms' => $data['terms'] ?? null,
                'status' => 'issued',
            ]);
            $rfq->vendors()->sync(collect($data['vendor_ids'])->mapWithKeys(fn ($id) => [$id => ['status' => 'invited']])->all());

            return $rfq->load(['purchaseRequest:id,pr_number,status', 'vendors:id,code,name', 'quotations.vendor:id,code,name', 'cba.selectedVendor:id,code,name']);
        });

        return response()->json(['success' => true, 'message' => 'RFQ berhasil dibuat dan vendor diundang.', 'data' => $this->formatRfq($rfq)], Response::HTTP_CREATED);
    }

    public function submitQuotation(Rfq $rfq, Request $request): JsonResponse
    {
        if (! in_array($rfq->status, ['issued', 'closed'], true)) {
            throw ValidationException::withMessages(['status' => 'Quotation hanya dapat dicatat untuk RFQ issued/closed.']);
        }
        $data = $request->validate([
            'vendor_id' => ['required', 'integer', 'exists:vendors,id'],
            'quotation_number' => ['nullable', 'string', 'max:80'],
            'quotation_date' => ['required', 'date'],
            'total_amount' => ['required', 'numeric', 'min:0.01'],
            'currency_code' => ['nullable', 'string', 'max:10'],
            'terms' => ['nullable', 'string'],
            'delivery_terms' => ['nullable', 'string'],
            'technical_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'financial_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        if (! $rfq->vendors()->where('vendors.id', $data['vendor_id'])->exists()) {
            throw ValidationException::withMessages(['vendor_id' => 'Vendor belum diundang dalam RFQ ini.']);
        }

        $technical = (float) ($data['technical_score'] ?? 0);
        $financial = (float) ($data['financial_score'] ?? 0);
        $quotation = VendorQuotation::updateOrCreate(
            ['rfq_id' => $rfq->id, 'vendor_id' => $data['vendor_id']],
            [
                ...$data,
                'currency_code' => $data['currency_code'] ?? 'IDR',
                'technical_score' => $technical,
                'financial_score' => $financial,
                'total_score' => round(($technical * 0.4) + ($financial * 0.6), 2),
                'status' => 'submitted',
                'created_by' => request()->user()->id,
            ],
        );
        $rfq->vendors()->updateExistingPivot($data['vendor_id'], ['status' => 'responded']);

        return response()->json(['success' => true, 'message' => 'Quotation vendor berhasil dicatat.', 'data' => $this->formatQuotation($quotation->load('vendor:id,code,name'))], Response::HTTP_CREATED);
    }

    public function closeRfq(Rfq $rfq): JsonResponse
    {
        if (! in_array($rfq->status, ['issued', 'closed'], true)) throw ValidationException::withMessages(['status' => 'RFQ hanya dapat ditutup dari status issued.']);
        $rfq->update(['status' => 'closed']);
        return response()->json(['success' => true, 'message' => 'RFQ berhasil ditutup.', 'data' => $this->formatRfq($rfq->fresh(['purchaseRequest:id,pr_number,status', 'vendors:id,code,name', 'quotations.vendor:id,code,name', 'cba.selectedVendor:id,code,name']))]);
    }

    public function cancelRfq(Rfq $rfq): JsonResponse
    {
        if (in_array($rfq->status, ['closed', 'cancelled'], true)) throw ValidationException::withMessages(['status' => 'RFQ tidak dapat dibatalkan dari status saat ini.']);
        $rfq->update(['status' => 'cancelled']);
        return response()->json(['success' => true, 'message' => 'RFQ berhasil dibatalkan.', 'data' => $this->formatRfq($rfq->fresh(['purchaseRequest:id,pr_number,status', 'vendors:id,code,name', 'quotations.vendor:id,code,name', 'cba.selectedVendor:id,code,name']))]);
    }

    public function createCba(Rfq $rfq, Request $request): JsonResponse
    {
        $data = $request->validate([
            'analysis_date' => ['required', 'date'],
            'selected_quotation_id' => ['required', 'integer', 'exists:vendor_quotations,id'],
            'selection_reason' => ['required', 'string'],
        ]);

        $quotation = VendorQuotation::where('rfq_id', $rfq->id)->findOrFail($data['selected_quotation_id']);

        $cba = DB::transaction(function () use ($rfq, $quotation, $data) {
            VendorQuotation::where('rfq_id', $rfq->id)->update(['status' => 'evaluated']);
            $quotation->update(['status' => 'selected']);
            $rfq->update(['status' => 'closed']);

            return ComparativeBidAnalysis::updateOrCreate(
                ['rfq_id' => $rfq->id],
                [
                    'cba_number' => $rfq->cba?->cba_number ?? 'CBA-'.now()->format('YmdHis').'-'.random_int(100, 999),
                    'analysis_date' => $data['analysis_date'],
                    'selected_vendor_id' => $quotation->vendor_id,
                    'selected_quotation_id' => $quotation->id,
                    'selection_reason' => $data['selection_reason'],
                    'status' => 'draft',
                ],
            )->load(['rfq.purchaseRequest:id,pr_number', 'selectedVendor:id,code,name', 'selectedQuotation']);
        });

        return response()->json(['success' => true, 'message' => 'CBA berhasil dibuat.', 'data' => $this->formatCba($cba)], Response::HTTP_CREATED);
    }

    public function approveCba(ComparativeBidAnalysis $cba): JsonResponse
    {
        if ($cba->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Hanya CBA draft yang dapat diapprove.']);
        }
        $cba->update(['status' => 'approved', 'approved_by' => request()->user()->id, 'approved_at' => now()]);

        return response()->json(['success' => true, 'message' => 'CBA berhasil diapprove.', 'data' => $this->formatCba($cba->fresh(['rfq.purchaseRequest:id,pr_number', 'selectedVendor:id,code,name', 'selectedQuotation']))]);
    }

    public function createPoFromCba(ComparativeBidAnalysis $cba, Request $request): JsonResponse
    {
        if ($cba->status !== 'approved') {
            throw ValidationException::withMessages(['status' => 'PO hanya dapat dibuat dari CBA approved.']);
        }
        $data = $request->validate([
            'po_date' => ['required', 'date'],
            'contract_number' => ['nullable', 'string', 'max:80'],
            'contract_date' => ['nullable', 'date'],
            'terms' => ['nullable', 'string'],
        ]);

        $cba->load(['rfq.purchaseRequest.lines']);
        $pr = $cba->rfq->purchaseRequest;

        $po = DB::transaction(function () use ($cba, $pr, $data) {
            $po = PurchaseOrder::create([
                'purchase_request_id' => $pr->id,
                'rfq_id' => $cba->rfq_id,
                'cba_id' => $cba->id,
                'vendor_id' => $cba->selected_vendor_id,
                'po_number' => 'PO-'.now()->format('YmdHis').'-'.random_int(100, 999),
                'contract_number' => $data['contract_number'] ?? null,
                'po_date' => $data['po_date'],
                'contract_date' => $data['contract_date'] ?? null,
                'terms' => $data['terms'] ?? $cba->selectedQuotation?->terms,
                'status' => 'draft',
            ]);

            foreach ($pr->lines as $line) {
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

        return response()->json(['success' => true, 'message' => 'PO/Contract berhasil dibuat dari CBA.', 'data' => [
            'id' => $po->id,
            'po_number' => $po->po_number,
            'contract_number' => $po->contract_number,
            'vendor_name' => $po->vendor?->name,
            'status' => $po->status,
            'total_amount' => $po->total_amount,
            'lines' => $po->lines,
        ]], Response::HTTP_CREATED);
    }

    private function formatRfq(Rfq $rfq): array
    {
        return [
            'id' => $rfq->id,
            'rfq_number' => $rfq->rfq_number,
            'pr_number' => $rfq->purchaseRequest?->pr_number,
            'purchase_request_id' => $rfq->purchase_request_id,
            'rfq_date' => $rfq->rfq_date?->toDateString(),
            'submission_deadline' => $rfq->submission_deadline?->toDateString(),
            'status' => $rfq->status,
            'vendors' => $rfq->vendors->map(fn ($vendor) => ['id' => $vendor->id, 'code' => $vendor->code, 'name' => $vendor->name, 'status' => $vendor->pivot?->status])->values(),
            'quotations' => $rfq->quotations->map(fn ($q) => $this->formatQuotation($q))->values(),
            'cba' => $rfq->cba ? $this->formatCba($rfq->cba) : null,
        ];
    }

    private function formatQuotation(VendorQuotation $quotation): array
    {
        return [
            'id' => $quotation->id,
            'vendor_id' => $quotation->vendor_id,
            'vendor_name' => $quotation->vendor?->name,
            'quotation_number' => $quotation->quotation_number,
            'quotation_date' => $quotation->quotation_date?->toDateString(),
            'total_amount' => $quotation->total_amount,
            'currency_code' => $quotation->currency_code,
            'terms' => $quotation->terms,
            'delivery_terms' => $quotation->delivery_terms,
            'technical_score' => $quotation->technical_score,
            'financial_score' => $quotation->financial_score,
            'total_score' => $quotation->total_score,
            'status' => $quotation->status,
        ];
    }

    private function formatCba(ComparativeBidAnalysis $cba): array
    {
        return [
            'id' => $cba->id,
            'cba_number' => $cba->cba_number,
            'rfq_id' => $cba->rfq_id,
            'rfq_number' => $cba->rfq?->rfq_number,
            'pr_number' => $cba->rfq?->purchaseRequest?->pr_number,
            'analysis_date' => $cba->analysis_date?->toDateString(),
            'selected_vendor_id' => $cba->selected_vendor_id,
            'selected_vendor_name' => $cba->selectedVendor?->name,
            'selected_quotation_id' => $cba->selected_quotation_id,
            'selection_reason' => $cba->selection_reason,
            'status' => $cba->status,
        ];
    }
}
