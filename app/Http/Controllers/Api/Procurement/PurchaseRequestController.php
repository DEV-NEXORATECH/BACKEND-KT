<?php

namespace App\Http\Controllers\Api\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Budget\BudgetCommitment;
use App\Models\Procurement\PurchaseRequest;
use App\Models\Master\ProcurementItem;
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
        'requester:id,name,email',
        'department:id,code,name',
        'project:id,code,name,program_id',
        'project.program:id,code,name',
        'vendor:id,code,name',
        'lines.budgetLine:id,line_code,description,total_amount,project_id',
        'lines.procurementItem:id,code,name,item_type,unit_of_measure_id,default_unit_price',
        'lines.procurementItem.unitOfMeasure:id,code,name',
    ];

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = $perPage < 1 || $perPage > 100 ? 10 : $perPage;

        $requests = PurchaseRequest::query()
            ->with($this->with)
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
        $payload = $this->validatePayload($request);
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

        $payload = $this->validatePayload($request, $purchaseRequest);

        $purchaseRequest = DB::transaction(function () use ($purchaseRequest, $payload) {
            $lines = $payload['lines'];
            unset($payload['lines']);

            $purchaseRequest->update($payload);
            $purchaseRequest->lines()->delete();
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
            'requester_id' => ['nullable', 'integer', 'exists:users,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'justification' => ['required', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.budget_line_id' => ['required', 'integer', 'exists:budget_lines,id'],
            'lines.*.procurement_item_id' => ['nullable', 'integer', 'exists:procurement_items,id'],
            'lines.*.item_description' => ['nullable', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0.01'],
        ]);

        foreach ($payload['lines'] as $index => $line) {
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
                    "lines.{$index}.item_description" => 'Pilih item/service master atau isi deskripsi item.',
                ]);
            }
            $quantity = round((float) $line['quantity'], 2);
            $unitPrice = round((float) $line['unit_price'], 2);
            $payload['lines'][$index]['quantity'] = $quantity;
            $payload['lines'][$index]['unit_price'] = $unitPrice;
            $payload['lines'][$index]['total_amount'] = round($quantity * $unitPrice, 2);
            $payload['lines'][$index]['line_order'] = $index + 1;
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
            'pr_number' => $purchaseRequest->pr_number,
            'request_date' => $purchaseRequest->request_date?->toDateString(),
            'requester_id' => $purchaseRequest->requester_id,
            'requester_name' => $purchaseRequest->requester?->name,
            'department_id' => $purchaseRequest->department_id,
            'department_name' => $purchaseRequest->department?->name,
            'project_id' => $purchaseRequest->project_id,
            'project_name' => $purchaseRequest->project?->name,
            'vendor_id' => $purchaseRequest->vendor_id,
            'vendor_name' => $purchaseRequest->vendor?->name,
            'justification' => $purchaseRequest->justification,
            'status' => $purchaseRequest->status,
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
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'total_amount' => $line->total_amount,
                'line_order' => $line->line_order,
            ])->values(),
            'submitted_at' => $purchaseRequest->submitted_at?->toISOString(),
            'approved_at' => $purchaseRequest->approved_at?->toISOString(),
            'rejected_at' => $purchaseRequest->rejected_at?->toISOString(),
            'created_at' => $purchaseRequest->created_at?->toISOString(),
        ];
    }
}
