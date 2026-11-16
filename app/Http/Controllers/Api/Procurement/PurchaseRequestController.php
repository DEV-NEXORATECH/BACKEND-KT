<?php

namespace App\Http\Controllers\Api\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Budget\BudgetCommitment;
use App\Models\Procurement\PurchaseRequest;
use App\Models\Master\ProcurementItem;
use App\Models\Master\BudgetLine;
use App\Models\Master\Project;
use App\Services\Budget\BudgetMonitoringService;
use App\Services\Settings\SystemPolicyService;
use App\Services\Approval\ApprovalWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class PurchaseRequestController extends Controller
{
    private array $with = [
        'requester:id,name,email,employee_id',
        'requester.employee.positionMaster',
        'department:id,code,name',
        'project:id,code,name,program_id,grant_agreement_id,relation_no',
        'project.program:id,code,name',
        'project.grantAgreement:id,grant_no,agreement_name,donor_id,relation_no',
        'project.grantAgreement.donor:id,code,name',
        'vendor:id,code,name,contact_person,contact_person_title,email,phone,address',
        'submittedBy:id,name,email',
        'approvedBy:id,name,email',
        'lines.budgetLine:id,line_code,description,total_amount,project_id,grant_agreement_id,gl_account_id,currency_id',
        'lines.budgetLine.glAccount:id,code,name',
        'lines.budgetLine.currency:id,code,name',
        'lines.budgetLine.grantAgreement:id,grant_no,agreement_name,donor_id',
        'lines.budgetLine.grantAgreement.donor:id,code,name',
        'lines.procurementItem:id,code,name,item_type,unit_of_measure_id,default_unit_price',
        'lines.procurementItem.unitOfMeasure:id,code,name',
    ];

    public function pdf(Request $request, PurchaseRequest $purchaseRequest)
    {
        if (! $this->canAccessAll($request) && (int) $purchaseRequest->requester_id !== (int) $request->user()->id) {
            abort(Response::HTTP_FORBIDDEN, 'Tidak boleh mengakses purchase request user lain.');
        }
        $purchaseRequest->load($this->with);
        $approvalSteps = collect(app(ApprovalWorkflowService::class)->progressFor('procurement', $purchaseRequest, $request->user())['steps'] ?? []);
        $budgetHolderStep = $approvalSteps->first(fn (array $step) => str_contains(strtolower((string) ($step['label'] ?? '')), 'budget')) ?? [];
        $procurementStep = $approvalSteps->first(fn (array $step) => str_contains(strtolower((string) ($step['label'] ?? '')), 'procurement')) ?? [];
        $budgetHolderName = $budgetHolderStep['approver_name'] ?? null;
        $procurementName = $procurementStep['approver_name'] ?? null;
        $rows = $purchaseRequest->lines->map(fn ($line, $index) => '<tr>'
            .'<td>'.e($line->account_code ?: $line->budgetLine?->glAccount?->code ?: '-').'</td>'
            .'<td>'.e($line->budget_code ?: $line->budgetLine?->line_code ?: '-').'</td>'
            .'<td>'.e($line->budgetLine?->description ?: '-').'</td>'
            .'<td>'.($line->line_order ?: $index + 1).'</td>'
            .'<td>'.e($line->item_description).'</td>'
            .'<td>'.e($line->unit ?: '-').'</td>'
            .'<td>'.e((string) $line->quantity).'</td>'
            .'<td>'.e($line->currency ?: 'IDR').'</td>'
            .'<td style="text-align:right">'.number_format((float) $line->unit_price, 2).'</td>'
            .'<td style="text-align:right">'.number_format((float) $line->total_amount, 2).'</td>'
            .'</tr>')->implode('');
        $html = '<style>body{font-family:DejaVu Sans,sans-serif;font-size:9px}h1{font-size:18px}table{width:100%;border-collapse:collapse;margin:8px 0}th,td{border:1px solid #777;padding:5px;vertical-align:top}.meta td:first-child{font-weight:bold;width:18%}.sign td{height:70px;width:33%}</style>'
            .'<h1>PURCHASE REQUEST '.e($purchaseRequest->pr_number).'</h1>'
            .'<table class="meta">'
            .'<tr><td>Country / Office</td><td>'.e(($purchaseRequest->country ?: '-').' / '.($purchaseRequest->office ?: '-')).'</td><td>Request Date</td><td>'.e($purchaseRequest->request_date?->format('d M Y') ?: '-').'</td></tr>'
            .'<tr><td>Requester</td><td>'.e($purchaseRequest->requester_name ?: $purchaseRequest->requester?->name ?: '-').'</td><td>Required Date</td><td>'.e($purchaseRequest->required_date?->format('d M Y') ?: '-').'</td></tr>'
            .'<tr><td>Email / Phone</td><td>'.e(($purchaseRequest->requester_email ?: '-').' / '.($purchaseRequest->requester_phone ?: '-')).'</td><td>Program / Function</td><td>'.e($purchaseRequest->requester_program ?: '-').'</td></tr>'
            .'<tr><td>Address / Location</td><td>'.e($purchaseRequest->requester_address ?: '-').'</td><td>Donor</td><td>'.e($purchaseRequest->donor_name ?: '-').'</td></tr>'
            .'<tr><td>Destination</td><td>'.e($purchaseRequest->destination ?: '-').'</td><td>Delivery / Incoterm</td><td>'.e(($purchaseRequest->delivery_method ?: '-').' / '.($purchaseRequest->preferred_incoterm ?: '-')).'</td></tr>'
            .'<tr><td>Selection Criteria</td><td>'.e($purchaseRequest->requirements ?: '-').'</td><td>Shipping Requirements</td><td>'.e($purchaseRequest->shipping_requirements ?: '-').'</td></tr>'
            .'</table>'
            .'<table><thead><tr><th>Account Code</th><th>Budget Code</th><th>Budget Line</th><th>Line Item</th><th>Description of Goods / Services</th><th>Unit / Form</th><th>Quantity</th><th>Currency</th><th>Estimated Unit Cost</th><th>Estimated Total Cost</th></tr></thead><tbody>'.$rows
            .'<tr><td colspan="9" style="text-align:right;font-weight:bold">TOTAL</td><td style="text-align:right;font-weight:bold">'.number_format((float) $purchaseRequest->total_amount, 2).'</td></tr></tbody></table>'
            .'<p><strong>Business Justification:</strong> '.e($purchaseRequest->justification).'</p>'
            .'<table class="sign"><tr>'
            .'<td><strong>Requester</strong><br>Name: '.e($purchaseRequest->requester_name ?: $purchaseRequest->requester?->name ?: '-').'<br><br>Signature:<br><br>Position: '.e($purchaseRequest->requester?->employee?->position ?: $purchaseRequest->requester?->employee?->positionMaster?->name ?: '-').'<br>Date: '.e($purchaseRequest->request_date?->format('d M Y') ?: '-').'</td>'
            .'<td><strong>Program Manager / Budget Holder</strong><br>Name: '.e($budgetHolderName ?: '-').'<br><br>Signature:<br><br>Position: '.e($budgetHolderStep['approver_position'] ?? '-').'<br>Date: '.e(isset($budgetHolderStep['approved_at']) ? substr((string) $budgetHolderStep['approved_at'], 0, 10) : '-').'</td>'
            .'<td><strong>Procurement</strong><br>Name: '.e($procurementName ?: $purchaseRequest->approvedBy?->name ?: '-').'<br><br>Signature:<br><br>Position: '.e($procurementStep['approver_position'] ?? '-').'<br>Date: '.e(isset($procurementStep['approved_at']) ? substr((string) $procurementStep['approved_at'], 0, 10) : '-').'</td>'
            .'</tr></table>';
        $filename = 'purchase-request-'.$purchaseRequest->pr_number.'.pdf';
        return response()->streamDownload(function () use ($html) {
            echo app('dompdf.wrapper')->loadHTML('<html><body>'.$html.'</body></html>')->output();
        }, $filename, ['Content-Type' => 'application/pdf']);
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = $perPage < 1 || $perPage > 100 ? 10 : $perPage;

        $requests = PurchaseRequest::query()
            ->with($this->with)
            ->when($request->filled('fiscal_year_id'), fn ($query) => $query->where('fiscal_year_id', (int) $request->query('fiscal_year_id')))
            ->when(! $this->canAccessAll($request), fn ($query) => $query->where('requester_id', $request->user()->id))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->query('search');
                $query->where(function ($inner) use ($search) {
                    $inner->where('pr_number', 'like', "%{$search}%")
                        ->orWhere('justification', 'like', "%{$search}%");
                });
            })
            ->latest('request_date')
            ->latest('id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Daftar purchase request berhasil dimuat.',
            'data' => $requests->getCollection()->map(fn (PurchaseRequest $request) => $this->format($request)),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->hydrateTemplateFields($this->validatePayload($request));
        if (! $this->canAccessAll($request)) {
            $payload['requester_id'] = $request->user()->id;
        }

        $purchaseRequest = DB::transaction(function () use ($payload, $request) {
            $lines = $payload['lines'];
            unset($payload['lines']);

            $purchaseRequest = PurchaseRequest::query()->create([
                ...$payload,
                'pr_number' => $payload['pr_number'] ?? $this->nextNumber(),
                'requester_id' => $payload['requester_id'] ?? $request->user()->id,
                'status' => 'draft',
            ]);

            $this->syncLines($purchaseRequest, $lines);

            return $purchaseRequest->load($this->with);
        });

        return response()->json([
            'success' => true,
            'message' => 'Purchase request berhasil dibuat.',
            'data' => $this->format($purchaseRequest),
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        $this->authorizeScope($request, $purchaseRequest);

        return response()->json([
            'success' => true,
            'message' => 'Detail purchase request berhasil dimuat.',
            'data' => $this->format($purchaseRequest->load($this->with)),
        ]);
    }

    public function update(Request $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        $this->authorizeScope($request, $purchaseRequest);

        if ($purchaseRequest->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Purchase request hanya dapat diubah saat status draft.',
            ]);
        }

        $payload = $this->hydrateTemplateFields($this->validatePayload($request, $purchaseRequest));

        $purchaseRequest = DB::transaction(function () use ($purchaseRequest, $payload) {
            $lines = $payload['lines'];
            unset($payload['lines']);

            $purchaseRequest->update($payload);
            $purchaseRequest->lines()->get()->each->delete();
            $this->syncLines($purchaseRequest, $lines);

            return $purchaseRequest->fresh()->load($this->with);
        });

        return response()->json([
            'success' => true,
            'message' => 'Purchase request berhasil diperbarui.',
            'data' => $this->format($purchaseRequest),
        ]);
    }

    public function destroy(Request $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        $this->authorizeScope($request, $purchaseRequest);

        if ($purchaseRequest->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Purchase request hanya dapat dihapus saat status draft.',
            ]);
        }

        $purchaseRequest->delete();

        return response()->json([
            'success' => true,
            'message' => 'Purchase request berhasil dihapus.',
        ]);
    }

    public function submit(Request $request, PurchaseRequest $purchaseRequest, ApprovalWorkflowService $workflow): JsonResponse
    {
        $this->authorizeScope($request, $purchaseRequest);

        $workflow->start('procurement', $purchaseRequest, (float) $purchaseRequest->total_amount, $request->user()->id);
        return $this->transition($purchaseRequest, 'draft', 'submitted', [
            'submitted_by' => request()->user()->id,
            'submitted_at' => now(),
        ], 'Purchase request berhasil disubmit.');
    }

    public function approve(Request $request, PurchaseRequest $purchaseRequest, BudgetMonitoringService $budgetService, SystemPolicyService $policies, ApprovalWorkflowService $workflow): JsonResponse
    {
        if ($purchaseRequest->status !== 'submitted') {
            throw ValidationException::withMessages([
                'status' => 'Purchase request harus berstatus submitted untuk approval.',
            ]);
        }

        $aggregated = $purchaseRequest->lines
            ->groupBy('budget_line_id')
            ->map(fn ($lines) => round((float) $lines->sum('total_amount'), 2));

        foreach ($aggregated as $budgetLineId => $amount) {
            $ownCommitted = (float) BudgetCommitment::query()
                ->where('source_type', PurchaseRequest::class)
                ->where('source_id', $purchaseRequest->id)
                ->where('budget_line_id', $budgetLineId)
                ->where('status', 'open')
                ->sum('amount');

            $netAmount = round($amount - $ownCommitted, 2);
            if ($netAmount <= 0) {
                continue;
            }
            $validation = $budgetService->validate($budgetLineId, $netAmount);
            if (! $validation['allowed'] && $policies->blocksOverBudget()) {
                throw ValidationException::withMessages([
                    'budget_line_id' => "Budget line {$budgetLineId}: {$validation['message']}",
                ]);
            }
        }

        $approval = $workflow->approve('procurement', $purchaseRequest, $request->user(), $request->input('notes'));
        if ($approval['managed'] && ! $approval['completed']) {
            return response()->json(['success' => true, 'message' => "Approval tahap selesai. Menunggu approver level {$approval['next_level']}.", 'data' => $this->format($purchaseRequest->fresh()->load($this->with))]);
        }

        $purchaseRequest = DB::transaction(function () use ($purchaseRequest, $aggregated) {
            $purchaseRequest->update([
                'status' => 'approved',
                'approved_by' => request()->user()->id,
                'approved_at' => now(),
                'decision_notes' => request('notes'),
            ]);

            foreach ($aggregated as $budgetLineId => $amount) {
                BudgetCommitment::query()->updateOrCreate(
                    [
                        'source_type' => PurchaseRequest::class,
                        'source_id' => $purchaseRequest->id,
                        'budget_line_id' => $budgetLineId,
                    ],
                    [
                        'reference' => $purchaseRequest->pr_number,
                        'amount' => $amount,
                        'status' => 'open',
                        'created_by' => request()->user()->id,
                    ],
                );
            }

            return $purchaseRequest->fresh()->load($this->with);
        });

        return response()->json([
            'success' => true,
            'message' => 'Purchase request approved dan budget commitment dibuat.',
            'data' => $this->format($purchaseRequest),
        ]);
    }

    public function reject(PurchaseRequest $purchaseRequest): JsonResponse
    {
        if (! in_array($purchaseRequest->status, ['submitted', 'approved'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Purchase request hanya dapat direject dari status submitted atau approved.',
            ]);
        }
        app(ApprovalWorkflowService::class)->reject('procurement', $purchaseRequest, request()->user(), (string) request('notes', 'Rejected'));

        $purchaseRequest = DB::transaction(function () use ($purchaseRequest) {
            $purchaseRequest->update([
                'status' => 'rejected',
                'rejected_by' => request()->user()->id,
                'rejected_at' => now(),
                'decision_notes' => request('notes'),
            ]);
            $this->releaseCommitments($purchaseRequest, 'cancelled');

            return $purchaseRequest->fresh()->load($this->with);
        });

        return response()->json([
            'success' => true,
            'message' => 'Purchase request berhasil direject.',
            'data' => $this->format($purchaseRequest),
        ]);
    }

    public function cancel(Request $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        $this->authorizeScope($request, $purchaseRequest);

        if (! in_array($purchaseRequest->status, ['draft', 'submitted', 'approved'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Purchase request tidak dapat dibatalkan dari status saat ini.',
            ]);
        }

        $purchaseRequest = DB::transaction(function () use ($purchaseRequest) {
            $purchaseRequest->update([
                'status' => 'cancelled',
                'decision_notes' => request('notes'),
            ]);
            $this->releaseCommitments($purchaseRequest, 'cancelled');

            return $purchaseRequest->fresh()->load($this->with);
        });

        return response()->json([
            'success' => true,
            'message' => 'Purchase request berhasil dibatalkan.',
            'data' => $this->format($purchaseRequest),
        ]);
    }

    private function validatePayload(Request $request, ?PurchaseRequest $purchaseRequest = null): array
    {
        $payload = $request->validate([
            'pr_number' => ['nullable', 'string', 'max:40', 'unique:purchase_requests,pr_number,'.($purchaseRequest?->id ?? 'NULL').',id'],
            'request_date' => ['required', 'date'],
            'required_date' => ['nullable', 'date', 'after_or_equal:request_date'],
            'country' => ['nullable', 'string', 'max:100'],
            'office' => ['nullable', 'string', 'max:100'],
            'requester_name' => ['nullable', 'string', 'max:150'],
            'requester_program' => ['nullable', 'string', 'max:150'],
            'requester_email' => ['nullable', 'email', 'max:150'],
            'requester_phone' => ['nullable', 'string', 'max:50'],
            'requester_address' => ['nullable', 'string', 'max:2000'],
            'destination' => ['nullable', 'string', 'max:255'],
            'donor_name' => ['nullable', 'string', 'max:255'],
            'delivery_method' => ['nullable', 'in:air,sea,hand_carry,road,courier,pickup'],
            'requirements' => ['nullable', 'string', 'max:5000'],
            'preferred_incoterm' => ['nullable', 'string', 'max:20'],
            'shipping_requirements' => ['nullable', 'string', 'max:5000'],
            'requester_id' => ['nullable', 'integer', 'exists:users,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'justification' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.budget_line_id' => ['required', 'integer', 'exists:budget_lines,id'],
            'lines.*.procurement_item_id' => ['nullable', 'integer', 'exists:procurement_items,id'],
            'lines.*.item_description' => ['nullable', 'string', 'max:255'],
            'lines.*.account_code' => ['nullable', 'string', 'max:50'],
            'lines.*.budget_code' => ['nullable', 'string', 'max:100'],
            'lines.*.source_of_fund' => ['nullable', 'string', 'max:255'],
            'lines.*.unit' => ['nullable', 'string', 'max:50'],
            'lines.*.currency' => ['nullable', 'string', 'max:10'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0.01'],
        ]);

        foreach ($payload['lines'] as $index => $line) {
            $budgetLine = BudgetLine::withoutGlobalScope('fiscal_year_context')
                ->with(['glAccount:id,code,name', 'currency:id,code,name', 'grantAgreement' => fn ($query) => $query->withoutGlobalScope('fiscal_year_context'), 'grantAgreement.donor:id,code,name'])
                ->findOrFail($line['budget_line_id']);
            if (! empty($payload['project_id']) && $budgetLine->project_id && (int) $budgetLine->project_id !== (int) $payload['project_id']) {
                throw ValidationException::withMessages([
                    "lines.{$index}.budget_line_id" => 'Budget line harus berasal dari project yang dipilih.',
                ]);
            }
            if (! empty($line['procurement_item_id'])) {
                $item = ProcurementItem::query()
                    ->where('is_active', true)
                    ->findOrFail($line['procurement_item_id']);
                if (blank($line['item_description'])) {
                    $payload['lines'][$index]['item_description'] = $item->name;
                }
            }
            if (blank($payload['lines'][$index]['item_description'] ?? null)) {
                throw ValidationException::withMessages([
                    "lines.{$index}.item_description" => 'Select an item or service from the master data, or enter an item description.',
                ]);
            }
            $quantity = round((float) $line['quantity'], 2);
            $unitPrice = round((float) $line['unit_price'], 2);
            $payload['lines'][$index]['quantity'] = $quantity;
            $payload['lines'][$index]['unit_price'] = $unitPrice;
            $payload['lines'][$index]['account_code'] = trim((string) ($line['account_code'] ?? '')) ?: $budgetLine->glAccount?->code;
            $payload['lines'][$index]['budget_code'] = trim((string) ($line['budget_code'] ?? '')) ?: $budgetLine->line_code;
            $payload['lines'][$index]['source_of_fund'] = trim((string) ($line['source_of_fund'] ?? ''))
                ?: ($budgetLine->grantAgreement?->donor?->name ?: $budgetLine->grantAgreement?->agreement_name);
            $payload['lines'][$index]['unit'] = trim((string) ($line['unit'] ?? '')) ?: null;
            $payload['lines'][$index]['currency'] = strtoupper(trim((string) ($line['currency'] ?? '')) ?: ($budgetLine->currency?->code ?: 'IDR'));
            $payload['lines'][$index]['total_amount'] = round($quantity * $unitPrice, 2);
            $payload['lines'][$index]['line_order'] = $index + 1;
        }

        // The Business Justification input is intentionally no longer shown
        // on the PR form. Keep the legacy non-null database column populated
        // with a concise summary derived from the requested goods/services.
        if (blank($payload['justification'] ?? null)) {
            $descriptions = collect($payload['lines'])
                ->pluck('item_description')
                ->filter()
                ->unique()
                ->take(3)
                ->implode(', ');
            $payload['justification'] = $descriptions !== ''
                ? 'Purchase request: '.$descriptions
                : 'Purchase request';
        }

        return $payload;
    }

    private function hydrateTemplateFields(array $payload): array
    {
        if (! empty($payload['project_id'])) {
            $project = Project::withoutGlobalScope('fiscal_year_context')
                ->with(['program:id,code,name', 'grantAgreement' => fn ($query) => $query->withoutGlobalScope('fiscal_year_context'), 'grantAgreement.donor:id,code,name'])
                ->findOrFail($payload['project_id']);
            $payload['requester_program'] = trim((string) ($payload['requester_program'] ?? '')) ?: $project->program?->name;
            $payload['donor_name'] = trim((string) ($payload['donor_name'] ?? '')) ?: $project->grantAgreement?->donor?->name;
        }

        return $payload;
    }

    private function canAccessAll(Request $request): bool
    {
        return $request->user()->hasAnyPermission([
            'procurement.pr.approve',
            'procurement.po.create',
            'procurement.rfq.create',
            'procurement.cba.create',
        ]);
    }

    private function authorizeScope(Request $request, PurchaseRequest $purchaseRequest): void
    {
        if (! $this->canAccessAll($request) && (int) $purchaseRequest->requester_id !== (int) $request->user()->id) {
            abort(Response::HTTP_FORBIDDEN, 'Tidak boleh mengakses purchase request user lain.');
        }
    }

    private function syncLines(PurchaseRequest $purchaseRequest, array $lines): void
    {
        foreach ($lines as $line) {
            $purchaseRequest->lines()->create($line);
        }
    }

    private function transition(PurchaseRequest $purchaseRequest, string $from, string $to, array $extra, string $message): JsonResponse
    {
        if ($purchaseRequest->status !== $from) {
            throw ValidationException::withMessages([
                'status' => "Purchase request harus berstatus {$from} untuk aksi ini.",
            ]);
        }

        $purchaseRequest->update([
            'status' => $to,
            ...$extra,
        ]);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $this->format($purchaseRequest->fresh()->load($this->with)),
        ]);
    }

    private function releaseCommitments(PurchaseRequest $purchaseRequest, string $status): void
    {
        BudgetCommitment::query()
            ->where('source_type', PurchaseRequest::class)
            ->where('source_id', $purchaseRequest->id)
            ->where('status', 'open')
            ->update([
                'status' => $status,
                'released_by' => request()->user()->id,
                'released_at' => now(),
            ]);
    }

    private function nextNumber(): string
    {
        return 'PR-'.now()->format('YmdHis').'-'.str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT);
    }

    private function format(PurchaseRequest $purchaseRequest): array
    {
        $purchaseRequest->loadMissing($this->with);

        return [
            'id' => $purchaseRequest->id,
            'fiscal_year_id' => $purchaseRequest->fiscal_year_id,
            'fiscal_year' => $purchaseRequest->fiscalYear?->only(['id', 'year', 'name']),
            'pr_number' => $purchaseRequest->pr_number,
            'request_date' => $purchaseRequest->request_date?->toDateString(),
            'required_date' => $purchaseRequest->required_date?->toDateString(),
            'country' => $purchaseRequest->country,
            'office' => $purchaseRequest->office,
            'requester_id' => $purchaseRequest->requester_id,
            'requester_name' => $purchaseRequest->requester_name ?: $purchaseRequest->requester?->name,
            'requester_program' => $purchaseRequest->requester_program ?: $purchaseRequest->project?->program?->name,
            'requester_email' => $purchaseRequest->requester_email ?: $purchaseRequest->requester?->email,
            'requester_phone' => $purchaseRequest->requester_phone,
            'requester_position' => $purchaseRequest->requester?->employee?->position ?: $purchaseRequest->requester?->employee?->positionMaster?->name,
            'requester_signature_url' => $purchaseRequest->requester?->employee?->signature_path ? url($purchaseRequest->requester->employee->signature_path) : null,
            'requester_address' => $purchaseRequest->requester_address,
            'destination' => $purchaseRequest->destination,
            'donor_name' => $purchaseRequest->donor_name ?: $purchaseRequest->project?->grantAgreement?->donor?->name,
            'delivery_method' => $purchaseRequest->delivery_method,
            'requirements' => $purchaseRequest->requirements,
            'preferred_incoterm' => $purchaseRequest->preferred_incoterm,
            'shipping_requirements' => $purchaseRequest->shipping_requirements,
            'department_id' => $purchaseRequest->department_id,
            'department_name' => $purchaseRequest->department?->name,
            'project_id' => $purchaseRequest->project_id,
            'project_name' => $purchaseRequest->project?->name,
            'relation_no' => $purchaseRequest->project?->relation_no,
            'grant_name' => $purchaseRequest->project?->grantAgreement?->agreement_name,
            'vendor_id' => $purchaseRequest->vendor_id,
            'vendor_name' => $purchaseRequest->vendor?->name,
            'vendor' => $purchaseRequest->vendor ? [
                'id' => $purchaseRequest->vendor->id,
                'code' => $purchaseRequest->vendor->code,
                'name' => $purchaseRequest->vendor->name,
                'contact_person' => $purchaseRequest->vendor->contact_person,
                'contact_person_title' => $purchaseRequest->vendor->contact_person_title,
                'email' => $purchaseRequest->vendor->email,
                'phone' => $purchaseRequest->vendor->phone,
                'address' => $purchaseRequest->vendor->address,
            ] : null,
            'justification' => $purchaseRequest->justification,
            'status' => $purchaseRequest->status,
            'approval_progress' => app(\App\Services\Approval\ApprovalWorkflowService::class)->progressFor('procurement', $purchaseRequest, request()->user()),
            'decision_notes' => $purchaseRequest->decision_notes,
            'total_amount' => $purchaseRequest->total_amount,
            'lines' => $purchaseRequest->lines->map(fn ($line) => [
                'id' => $line->id,
                'budget_line_id' => $line->budget_line_id,
                'budget_line_code' => $line->budgetLine?->line_code,
                'budget_line_description' => $line->budgetLine?->description,
                'procurement_item_id' => $line->procurement_item_id,
                'procurement_item_code' => $line->procurementItem?->code,
                'procurement_item_name' => $line->procurementItem?->name,
                'item_type' => $line->procurementItem?->item_type,
                'unit_of_measure' => $line->procurementItem?->unitOfMeasure?->name,
                'item_description' => $line->item_description,
                'account_code' => $line->account_code ?: $line->budgetLine?->glAccount?->code,
                'budget_code' => $line->budget_code ?: $line->budgetLine?->line_code,
                'source_of_fund' => $line->source_of_fund ?: ($line->budgetLine?->grantAgreement?->donor?->name ?: $line->budgetLine?->grantAgreement?->agreement_name),
                'unit' => $line->unit ?: $line->procurementItem?->unitOfMeasure?->name,
                'currency' => $line->currency ?: ($line->budgetLine?->currency?->code ?: 'IDR'),
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'total_amount' => $line->total_amount,
                'line_order' => $line->line_order,
            ])->values(),
            'submitted_at' => $purchaseRequest->submitted_at?->toISOString(),
            'submitted_by_name' => $purchaseRequest->submittedBy?->name,
            'approved_at' => $purchaseRequest->approved_at?->toISOString(),
            'approved_by_name' => $purchaseRequest->approvedBy?->name,
            'rejected_at' => $purchaseRequest->rejected_at?->toISOString(),
            'created_at' => $purchaseRequest->created_at?->toISOString(),
        ];
    }
}
