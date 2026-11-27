<?php

namespace App\Http\Controllers\Api\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Procurement\ComparativeBidAnalysis;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseRequest;
use App\Models\Procurement\Rfq;
use App\Models\Procurement\VendorQuotation;
use App\Services\Rbac\DataScopeService;
use App\Services\Approval\ApprovalWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AdvancedProcurementController extends Controller
{
    private function procurementThreshold(float $amount): array
    {
        return match (true) {
            $amount <= 2000000 => ['process' => 'direct', 'min_quotes' => 0, 'requires_cba' => false],
            $amount <= 10000000 => ['process' => 'direct', 'min_quotes' => 1, 'requires_cba' => false],
            $amount <= 50000000 => ['process' => 'limited_tender', 'min_quotes' => 3, 'requires_cba' => true],
            $amount <= 150000000 => ['process' => 'limited_tender', 'min_quotes' => 3, 'requires_cba' => true],
            $amount <= 250000000 => ['process' => 'limited_tender', 'min_quotes' => 5, 'requires_cba' => true],
            default => ['process' => 'open_tender', 'min_quotes' => 5, 'requires_cba' => true, 'tender_committee_size' => 5],
        };
    }

    public function rfqs(Request $request): JsonResponse
    {
        $query = $this->rfqListingQuery($request);
        app(DataScopeService::class)->applyRelatedProjectScope($query, $request->user(), 'created_by', 'purchaseRequest');
        return $this->rfqResponse($query, $request);
    }

    public function cbas(Request $request): JsonResponse
    {
        $query = $this->rfqListingQuery($request)->whereHas('quotations');
        app(DataScopeService::class)->applyRelatedProjectScope($query, $request->user(), 'created_by', 'purchaseRequest');
        $rfqRows = $query->latest('id')->get()->map(fn (Rfq $rfq) => $this->formatRfq($rfq));
        $standalone = ComparativeBidAnalysis::with(['purchaseRequest.lines', 'selectedVendor:id,code,name', 'selectedQuotation'])
            ->whereNull('rfq_id')
            ->whereNotNull('purchase_request_id')
            ->when($request->filled('fiscal_year_id'), fn ($q) => $q->whereHas('purchaseRequest', fn ($pr) => $pr->where('fiscal_year_id', (int) $request->query('fiscal_year_id'))))
            ->get()
            ->map(function (ComparativeBidAnalysis $cba) {
                return [
                    'id' => null,
                    'rfq_number' => null,
                    'rfq_date' => $cba->analysis_date?->toDateString(),
                    'purchase_request' => $cba->purchaseRequest,
                    'quotations' => [],
                    'cba' => $this->formatCba($cba),
                ];
            });
        $items = $rfqRows->concat($standalone)->sortByDesc('rfq_date')->values();
        return response()->json(['success' => true, 'data' => $items, 'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => $items->count(), 'total' => $items->count()]]);
    }

    private function rfqResponse($query, Request $request): JsonResponse
    {
        $query = $request->query('sort') === 'oldest' ? $query->oldest('id') : $query->latest('id');
        $rows = $request->has('page') || $request->has('per_page')
            ? $query->paginate(min(100, max(1, (int) $request->query('per_page', 10))))
            : null;
        $items = $rows ? $rows->items() : $query->get();
        return response()->json(['success' => true, 'data' => collect($items)->map(fn (Rfq $rfq) => $this->formatRfq($rfq)), 'meta' => $rows ? ['current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'per_page' => $rows->perPage(), 'total' => $rows->total()] : null]);
    }

    private function rfqListingQuery(Request $request)
    {
        return Rfq::with(['purchaseRequest.lines', 'purchaseRequest.project:id,name', 'purchaseRequest.vendor:id,code,name', 'vendors:id,code,name,address', 'quotations.vendor:id,code,name,address', 'cba.selectedVendor:id,code,name,address'])
            ->when($request->filled('fiscal_year_id'), fn ($query) => $query->where('fiscal_year_id', (int) $request->query('fiscal_year_id')));
    }

    public function createRfq(PurchaseRequest $purchaseRequest, Request $request): JsonResponse
    {
        if ($purchaseRequest->status !== 'approved') {
            throw ValidationException::withMessages(['status' => 'RFQ hanya dapat dibuat dari PR approved.']);
        }

        $data = $request->validate([
            'rfq_date' => ['required', 'date'],
            'submission_deadline' => ['nullable', 'date'],
            'required_delivery_date' => ['nullable', 'date'],
            'delivery_method' => ['nullable', 'string', 'max:30'],
            'payment_terms' => ['nullable', 'string', 'max:120'],
            'supplier_vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
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
            'vendor_ids' => ['required', 'array', 'min:1'],
            'vendor_ids.*' => ['integer', 'exists:vendors,id'],
        ]);

        $policy = $this->procurementThreshold((float) $purchaseRequest->total_amount);
        if (count($data['vendor_ids']) < $policy['min_quotes']) {
            throw ValidationException::withMessages([
                'vendor_ids' => "Nilai PR membutuhkan minimal {$policy['min_quotes']} quotation/vendor sesuai threshold procurement.",
            ]);
        }

        $rfq = DB::transaction(function () use ($purchaseRequest, $data) {
            $rfq = Rfq::create([
                'purchase_request_id' => $purchaseRequest->id,
                'rfq_number' => $this->nextRfqNumber(),
                'rfq_date' => $data['rfq_date'],
                'submission_deadline' => $data['submission_deadline'] ?? null,
                'required_delivery_date' => $data['required_delivery_date'] ?? null,
                'delivery_method' => $data['delivery_method'] ?? null,
                'payment_terms' => $data['payment_terms'] ?? null,
                'supplier_vendor_id' => $data['supplier_vendor_id'] ?? null,
                'supplier_contact_name' => $data['supplier_contact_name'] ?? null,
                'supplier_email' => $data['supplier_email'] ?? null,
                'supplier_phone' => $data['supplier_phone'] ?? null,
                'supplier_fax' => $data['supplier_fax'] ?? null,
                'supplier_mobile' => $data['supplier_mobile'] ?? null,
                'supplier_address' => $data['supplier_address'] ?? null,
                'procurement_contact_name' => $data['procurement_contact_name'] ?? null,
                'procurement_contact_email' => $data['procurement_contact_email'] ?? null,
                'procurement_address' => $data['procurement_address'] ?? null,
                'other_tax' => $data['other_tax'] ?? 0,
                'other_charges' => $data['other_charges'] ?? 0,
                'checked_by' => $data['checked_by'] ?? null,
                'checked_by_title' => $data['checked_by_title'] ?? null,
                'budget_holder_name' => $data['budget_holder_name'] ?? null,
                'budget_holder_title' => $data['budget_holder_title'] ?? null,
                'supplier_acceptance_name' => $data['supplier_acceptance_name'] ?? null,
                'supplier_acceptance_title' => $data['supplier_acceptance_title'] ?? null,
                'terms' => $data['terms'] ?? null,
                'status' => 'issued',
                'created_by' => request()->user()->id,
            ]);
            $rfq->vendors()->sync(collect($data['vendor_ids'])->mapWithKeys(fn ($id) => [$id => ['status' => 'invited']])->all());

            return $rfq->load(['purchaseRequest:id,pr_number,status', 'vendors:id,code,name', 'quotations.vendor:id,code,name', 'cba.selectedVendor:id,code,name']);
        });

        return response()->json(['success' => true, 'message' => 'RFQ berhasil dibuat sebagai dokumen internal; tidak dikirim ke vendor melalui sistem.', 'data' => $this->formatRfq($rfq)], Response::HTTP_CREATED);
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
        $rfq->loadMissing('purchaseRequest');
        $policy = $this->procurementThreshold((float) $rfq->purchaseRequest?->total_amount);
        if (! $policy['requires_cba']) {
            throw ValidationException::withMessages([
                'status' => 'CBA hanya wajib untuk nilai pengadaan mulai Rp10.000.001.',
            ]);
        }
        if ($rfq->quotations()->count() < $policy['min_quotes']) {
            throw ValidationException::withMessages([
                'quotations' => "CBA membutuhkan minimal {$policy['min_quotes']} quotation yang tercatat.",
            ]);
        }
        if (($policy['tender_committee_size'] ?? 0) === 5) {
            $request->validate([
                'panelist_1_name' => ['required', 'string', 'max:120'],
                'panelist_2_name' => ['required', 'string', 'max:120'],
                'panelist_3_name' => ['required', 'string', 'max:120'],
                'panelist_4_name' => ['required', 'string', 'max:120'],
                'panelist_5_name' => ['required', 'string', 'max:120'],
            ]);
        }
        $data = $request->validate([
            'analysis_date' => ['required', 'date'],
            'selected_vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'selected_quotation_id' => ['required', 'integer', 'exists:vendor_quotations,id'],
            'selection_reason' => ['required', 'string'],
            'remarks' => ['nullable', 'string'],
            'bidding_results' => ['nullable', 'string'],
            'prepared_by' => ['nullable', 'string', 'max:120'],
            'prepared_by_position' => ['nullable', 'string', 'max:120'],
            'budget_holder_name' => ['nullable', 'string', 'max:120'],
            'budget_holder_position' => ['nullable', 'string', 'max:120'],
            'panelist_1_name' => ['nullable', 'string', 'max:120'],
            'panelist_1_position' => ['nullable', 'string', 'max:120'],
            'panelist_2_name' => ['nullable', 'string', 'max:120'],
            'panelist_2_position' => ['nullable', 'string', 'max:120'],
            'panelist_3_name' => ['nullable', 'string', 'max:120'],
            'panelist_3_position' => ['nullable', 'string', 'max:120'],
            'panelist_4_name' => ['nullable', 'string', 'max:120'],
            'panelist_4_position' => ['nullable', 'string', 'max:120'],
            'panelist_5_name' => ['nullable', 'string', 'max:120'],
            'panelist_5_position' => ['nullable', 'string', 'max:120'],
            'bid_comparison' => ['nullable', 'array'],
        ]);

        $quotation = VendorQuotation::where('rfq_id', $rfq->id)->findOrFail($data['selected_quotation_id']);

        $cba = DB::transaction(function () use ($rfq, $quotation, $data) {
            VendorQuotation::where('rfq_id', $rfq->id)->update(['status' => 'evaluated']);
            $quotation->update(['status' => 'selected']);
            $rfq->update(['status' => 'closed']);

            return ComparativeBidAnalysis::updateOrCreate(
                ['rfq_id' => $rfq->id],
                [
                    'cba_number' => $rfq->cba?->cba_number ?? $this->nextCbaNumber(),
                    'analysis_date' => $data['analysis_date'],
                    'selected_vendor_id' => $quotation->vendor_id,
                    'selected_quotation_id' => $quotation->id,
                    'selection_reason' => $data['selection_reason'],
                    'remarks' => $data['remarks'] ?? null,
                    'bidding_results' => $data['bidding_results'] ?? null,
                    'prepared_by' => $data['prepared_by'] ?? null,
                    'prepared_by_position' => $data['prepared_by_position'] ?? null,
                    'budget_holder_name' => $data['budget_holder_name'] ?? null,
                    'budget_holder_position' => $data['budget_holder_position'] ?? null,
                    'panelist_1_name' => $data['panelist_1_name'] ?? null,
                    'panelist_1_position' => $data['panelist_1_position'] ?? null,
                    'panelist_2_name' => $data['panelist_2_name'] ?? null,
                    'panelist_2_position' => $data['panelist_2_position'] ?? null,
                    'panelist_3_name' => $data['panelist_3_name'] ?? null,
                    'panelist_3_position' => $data['panelist_3_position'] ?? null,
                    'panelist_4_name' => $data['panelist_4_name'] ?? null,
                    'panelist_4_position' => $data['panelist_4_position'] ?? null,
                    'panelist_5_name' => $data['panelist_5_name'] ?? null,
                    'panelist_5_position' => $data['panelist_5_position'] ?? null,
                    'bid_comparison' => $data['bid_comparison'] ?? null,
                    'status' => 'draft',
                ],
            )->load(['rfq.purchaseRequest:id,pr_number', 'selectedVendor:id,code,name', 'selectedQuotation']);
        });

        return response()->json(['success' => true, 'message' => 'CBA berhasil dibuat.', 'data' => $this->formatCba($cba)], Response::HTTP_CREATED);
    }

    public function createCbaFromPurchaseRequest(PurchaseRequest $purchaseRequest, Request $request): JsonResponse
    {
        if ($purchaseRequest->status !== 'approved') {
            throw ValidationException::withMessages(['status' => 'CBA hanya dapat dibuat dari PR approved.']);
        }

        $data = $request->validate([
            'analysis_date' => ['required', 'date'],
            'selection_reason' => ['required', 'string'],
            'remarks' => ['nullable', 'string'],
            'bidding_results' => ['nullable', 'string'],
            'prepared_by' => ['nullable', 'string', 'max:120'],
            'prepared_by_position' => ['nullable', 'string', 'max:120'],
            'budget_holder_name' => ['nullable', 'string', 'max:120'],
            'budget_holder_position' => ['nullable', 'string', 'max:120'],
            'bid_comparison' => ['nullable', 'array'],
        ]);

        $cba = ComparativeBidAnalysis::updateOrCreate(
            ['purchase_request_id' => $purchaseRequest->id, 'rfq_id' => null],
            [...$data, 'selected_vendor_id' => $data['selected_vendor_id'] ?? null, 'cba_number' => $this->nextCbaNumber(), 'status' => 'draft', 'created_by' => $request->user()->id],
        );

        return response()->json(['success' => true, 'message' => 'CBA berhasil dibuat dari PR. RFQ dapat diproses paralel.', 'data' => $this->formatCba($cba->load('purchaseRequest'))], Response::HTTP_CREATED);
    }

    public function submitCba(Request $request, ComparativeBidAnalysis $cba, ApprovalWorkflowService $workflow): JsonResponse
    {
        if ($cba->status !== 'draft') throw ValidationException::withMessages(['status' => 'Hanya CBA draft yang dapat diajukan approval.']);
        $run = $workflow->start('cba', $cba, (float) ($cba->selectedQuotation?->total_amount ?? 0), $request->user()->id);
        return response()->json(['success' => true, 'workflow_managed' => (bool) $run, 'message' => $run ? 'CBA diajukan ke Approval Matrix.' : 'Tidak ada Approval Matrix CBA aktif; CBA siap diapprove.', 'data' => $this->formatCba($cba->fresh(['rfq.purchaseRequest:id,pr_number', 'selectedVendor:id,code,name', 'selectedQuotation']))]);
    }

    public function approveCba(Request $request, ComparativeBidAnalysis $cba, ApprovalWorkflowService $workflow): JsonResponse
    {
        if ($cba->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Hanya CBA draft yang dapat diapprove.']);
        }
        $amount = (float) ($cba->selectedQuotation?->total_amount ?? 0);
        $approval = $workflow->approve('cba', $cba, $request->user(), $request->input('notes'));
        if (! $approval['managed'] && $workflow->requiresApproval('cba', $cba, $amount)) throw ValidationException::withMessages(['approval' => 'CBA harus disubmit ke Approval Matrix sebelum approval.']);
        if ($approval['managed'] && ! $approval['completed']) return response()->json(['success' => true, 'message' => "Approval CBA tahap selesai. Menunggu approver level {$approval['next_level']}.", 'data' => $this->formatCba($cba->fresh(['rfq.purchaseRequest:id,pr_number', 'selectedVendor:id,code,name', 'selectedQuotation']))]);
        $cba->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]);

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

        $cba->load(['rfq.purchaseRequest.lines', 'purchaseRequest.lines']);
        $pr = $cba->rfq?->purchaseRequest ?? $cba->purchaseRequest;
        if (! $pr) {
            throw ValidationException::withMessages(['purchase_request' => 'CBA belum terhubung ke Purchase Request.']);
        }
        if (! $cba->selected_vendor_id) {
            throw ValidationException::withMessages(['vendor' => 'Vendor wajib dipilih sebelum PO dibuat dari CBA langsung.']);
        }

        $po = DB::transaction(function () use ($cba, $pr, $data) {
            $po = PurchaseOrder::create([
                'purchase_request_id' => $pr->id,
                'rfq_id' => $cba->rfq_id,
                'cba_id' => $cba->id,
                'vendor_id' => $cba->selected_vendor_id,
                'po_number' => $this->nextPoNumber(),
                'contract_number' => $data['contract_number'] ?? null,
                'po_date' => $data['po_date'],
                'contract_date' => $data['contract_date'] ?? null,
                'terms' => $data['terms'] ?? $cba->selectedQuotation?->terms,
                'status' => 'draft',
                'created_by' => request()->user()->id,
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

    public function createPoFromRfq(Rfq $rfq, Request $request): JsonResponse
    {
        $rfq->load(['purchaseRequest.lines', 'quotations']);
        $amount = (float) ($rfq->purchaseRequest?->total_amount ?? 0);
        $policy = $this->procurementThreshold($amount);
        if ($amount <= 2000000 || $policy['requires_cba']) {
            throw ValidationException::withMessages(['status' => 'Threshold ini tidak menggunakan jalur PO langsung dari RFQ.']);
        }
        if ($rfq->quotations()->count() < $policy['min_quotes']) {
            throw ValidationException::withMessages(['quotations' => "Minimal {$policy['min_quotes']} quotation harus tercatat sebelum PO/Contract."]);
        }
        $data = $request->validate([
            'selected_quotation_id' => ['required', 'integer', 'exists:vendor_quotations,id'],
            'justification_of_decision' => ['required', 'string', 'max:2000'],
            'po_date' => ['required', 'date'],
            'contract_number' => ['nullable', 'string', 'max:80'],
            'contract_date' => ['nullable', 'date'],
            'terms' => ['nullable', 'string'],
        ]);
        $quotation = $rfq->quotations()->whereKey($data['selected_quotation_id'])->firstOrFail();
        $pr = $rfq->purchaseRequest;
        $po = DB::transaction(function () use ($rfq, $pr, $quotation, $data) {
            $po = PurchaseOrder::create([
                'purchase_request_id' => $pr->id,
                'rfq_id' => $rfq->id,
                'vendor_id' => $quotation->vendor_id,
                'po_number' => $this->nextPoNumber(),
                'contract_number' => $data['contract_number'] ?? null,
                'po_date' => $data['po_date'],
                'contract_date' => $data['contract_date'] ?? null,
                'terms' => trim(($data['terms'] ?? '')."\nJustification of decision: ".$data['justification_of_decision']),
                'status' => 'draft',
                'created_by' => request()->user()->id,
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
        return response()->json(['success' => true, 'message' => 'PO/Contract berhasil dibuat dari RFQ.', 'data' => [
            'id' => $po->id, 'po_number' => $po->po_number, 'contract_number' => $po->contract_number,
            'vendor_name' => $po->vendor?->name, 'status' => $po->status, 'total_amount' => $po->total_amount, 'lines' => $po->lines,
        ]], Response::HTTP_CREATED);
    }

    private function formatRfq(Rfq $rfq): array
    {
        return [
            'id' => $rfq->id,
            'fiscal_year_id' => $rfq->fiscal_year_id,
            'fiscal_year' => $rfq->fiscalYear?->only(['id', 'year', 'name']),
            'rfq_number' => $rfq->rfq_number,
            'pr_number' => $rfq->purchaseRequest?->pr_number,
            'purchase_request_id' => $rfq->purchase_request_id,
            'project_name' => $rfq->purchaseRequest?->project?->name,
            'donor_name' => $rfq->purchaseRequest?->donor_name,
            'vendor_name' => $rfq->purchaseRequest?->vendor?->name,
            'total_amount' => $rfq->purchaseRequest?->total_amount,
            'rfq_date' => $rfq->rfq_date?->toDateString(),
            'submission_deadline' => $rfq->submission_deadline?->toDateString(),
            'required_delivery_date' => $rfq->required_delivery_date?->toDateString(),
            'delivery_method' => $rfq->delivery_method,
            'payment_terms' => $rfq->payment_terms,
            'supplier_vendor_id' => $rfq->supplier_vendor_id,
            'supplier_contact_name' => $rfq->supplier_contact_name,
            'supplier_email' => $rfq->supplier_email,
            'supplier_phone' => $rfq->supplier_phone,
            'supplier_fax' => $rfq->supplier_fax,
            'supplier_mobile' => $rfq->supplier_mobile,
            'supplier_address' => $rfq->supplier_address,
            'procurement_contact_name' => $rfq->procurement_contact_name,
            'procurement_contact_email' => $rfq->procurement_contact_email,
            'procurement_address' => $rfq->procurement_address,
            'other_tax' => $rfq->other_tax,
            'other_charges' => $rfq->other_charges,
            'checked_by' => $rfq->checked_by,
            'checked_by_title' => $rfq->checked_by_title,
            'budget_holder_name' => $rfq->budget_holder_name,
            'budget_holder_title' => $rfq->budget_holder_title,
            'supplier_acceptance_name' => $rfq->supplier_acceptance_name,
            'supplier_acceptance_title' => $rfq->supplier_acceptance_title,
            'status' => $rfq->status,
            'purchase_request' => $rfq->purchaseRequest,
            'vendors' => $rfq->vendors->map(fn ($vendor) => ['id' => $vendor->id, 'code' => $vendor->code, 'name' => $vendor->name, 'address' => $vendor->address, 'status' => $vendor->pivot?->status])->values(),
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
            'vendor_address' => $quotation->vendor?->address,
            'vendor' => $quotation->vendor?->only(['id', 'code', 'name', 'address']),
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
            'fiscal_year_id' => $cba->rfq?->fiscal_year_id,
            'fiscal_year' => $cba->rfq?->fiscalYear?->only(['id', 'year', 'name']),
            'cba_number' => $cba->cba_number,
            'rfq_id' => $cba->rfq_id,
            'purchase_request_id' => $cba->purchase_request_id,
            'rfq_number' => $cba->rfq?->rfq_number,
            'pr_number' => $cba->rfq?->purchaseRequest?->pr_number ?? $cba->purchaseRequest?->pr_number,
            'analysis_date' => $cba->analysis_date?->toDateString(),
            'selected_vendor_id' => $cba->selected_vendor_id,
            'selected_vendor_name' => $cba->selectedVendor?->name,
            'selected_quotation_id' => $cba->selected_quotation_id,
            'selection_reason' => $cba->selection_reason,
            'remarks' => $cba->remarks,
            'bidding_results' => $cba->bidding_results,
            'prepared_by' => $cba->prepared_by,
            'prepared_by_position' => $cba->prepared_by_position,
            'budget_holder_name' => $cba->budget_holder_name,
            'budget_holder_position' => $cba->budget_holder_position,
            'panelist_1_name' => $cba->panelist_1_name,
            'panelist_1_position' => $cba->panelist_1_position,
            'panelist_2_name' => $cba->panelist_2_name,
            'panelist_2_position' => $cba->panelist_2_position,
            'panelist_3_name' => $cba->panelist_3_name,
            'panelist_3_position' => $cba->panelist_3_position,
            'panelist_4_name' => $cba->panelist_4_name,
            'panelist_4_position' => $cba->panelist_4_position,
            'panelist_5_name' => $cba->panelist_5_name,
            'panelist_5_position' => $cba->panelist_5_position,
            'bid_comparison' => $cba->bid_comparison,
            'status' => $cba->status,
            'approval_progress' => app(\App\Services\Approval\ApprovalWorkflowService::class)->progressFor('cba', $cba, request()->user()),
        ];
    }

    private function nextRfqNumber(): string
    {
        $prefix = 'RFQ-' . now()->format('Ymd') . '-';
        $latest = Rfq::withoutGlobalScopes()->where('rfq_number', 'LIKE', $prefix . '%')->orderByDesc('id')->value('rfq_number');
        $seq = 1;
        if ($latest && preg_match('/-(\d+)$/', $latest, $m)) $seq = ((int) $m[1]) + 1;
        $candidate = $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
        while (Rfq::withoutGlobalScopes()->where('rfq_number', $candidate)->exists()) {
            $seq++;
            $candidate = $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
        }
        return $candidate;
    }

    private function nextCbaNumber(): string
    {
        $prefix = 'CBA-' . now()->format('Ymd') . '-';
        $latest = ComparativeBidAnalysis::withoutGlobalScopes()->where('cba_number', 'LIKE', $prefix . '%')->orderByDesc('id')->value('cba_number');
        $seq = 1;
        if ($latest && preg_match('/-(\d+)$/', $latest, $m)) $seq = ((int) $m[1]) + 1;
        $candidate = $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
        while (ComparativeBidAnalysis::withoutGlobalScopes()->where('cba_number', $candidate)->exists()) {
            $seq++;
            $candidate = $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
        }
        return $candidate;
    }

    private function nextPoNumber(): string
    {
        $prefix = 'PO-' . now()->format('Ymd') . '-';
        $latest = PurchaseOrder::withoutGlobalScopes()->where('po_number', 'LIKE', $prefix . '%')->orderByDesc('id')->value('po_number');
        $seq = 1;
        if ($latest && preg_match('/-(\d+)$/', $latest, $m)) $seq = ((int) $m[1]) + 1;
        $candidate = $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
        while (PurchaseOrder::withoutGlobalScopes()->where('po_number', $candidate)->exists()) {
            $seq++;
            $candidate = $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
        }
        return $candidate;
    }
}
