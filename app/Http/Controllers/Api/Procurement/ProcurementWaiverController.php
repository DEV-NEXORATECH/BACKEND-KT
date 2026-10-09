<?php

namespace App\Http\Controllers\Api\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Procurement\ProcurementWaiver;
use App\Models\Procurement\PurchaseRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProcurementWaiverController extends Controller
{
    private array $with = [
        'purchaseRequest:id,pr_number',
        'project:id,code,name',
        'vendor:id,code,name',
        'approver:id,name,email',
        'creator:id,name,email',
    ];

    public function index(Request $request): JsonResponse
    {
        $query = ProcurementWaiver::query()->with($this->with);

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('waiver_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('justification', 'like', "%{$search}%")
                    ->orWhere('vendor_name', 'like', "%{$search}%")
                    ->orWhere('project_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $sort = $request->query('sort', 'newest');
        if ($sort === 'oldest') {
            $query->orderBy('date', 'asc')->orderBy('id', 'asc');
        } else {
            $query->orderBy('date', 'desc')->orderBy('id', 'desc');
        }

        $perPage = (int) $request->query('per_page', 50);
        $perPage = $perPage < 1 || $perPage > 200 ? 50 : $perPage;

        $waivers = $query->paginate($perPage);

        $items = collect($waivers->items())->map(function (ProcurementWaiver $w) {
            $arr = $w->toArray();
            $arr['amount'] = (float) $w->total_amount;
            $arr['pr_id'] = $w->purchase_request_id;
            $arr['pr_number'] = $w->purchaseRequest?->pr_number;
            return $arr;
        });

        return response()->json([
            'success' => true,
            'message' => 'Daftar waiver berhasil dimuat.',
            'data' => $items,
            'meta' => [
                'current_page' => $waivers->currentPage(),
                'last_page' => $waivers->lastPage(),
                'per_page' => $waivers->perPage(),
                'total' => $waivers->total(),
            ],
        ]);
    }

    public function show(ProcurementWaiver $waiver): JsonResponse
    {
        $data = $waiver->load($this->with)->toArray();
        $data['amount'] = (float) $waiver->total_amount;
        $data['pr_id'] = $waiver->purchase_request_id;
        $data['pr_number'] = $waiver->purchaseRequest?->pr_number;

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $input = $request->all();
        if (isset($input['pr_id']) && !isset($input['purchase_request_id'])) {
            $input['purchase_request_id'] = $input['pr_id'];
        }
        if (isset($input['amount']) && !isset($input['total_amount'])) {
            $input['total_amount'] = $input['amount'];
        }

        $data = validator($input, [
            'waiver_number' => ['nullable', 'string', 'max:80', 'unique:procurement_waivers,waiver_number'],
            'purchase_request_id' => ['nullable', 'integer', 'exists:purchase_requests,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'project_name' => ['nullable', 'string', 'max:200'],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'vendor_name' => ['nullable', 'string', 'max:200'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'description' => ['required', 'string', 'max:255'],
            'justification' => ['required', 'string'],
            'attachment_name' => ['nullable', 'string', 'max:255'],
            'attachment_path' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:pending,approved,rejected'],
        ])->validate();

        if (empty($data['waiver_number'])) {
            $data['waiver_number'] = 'WVR-' . now()->format('Y') . '-' . str_pad((string) (ProcurementWaiver::withTrashed()->count() + 1), 3, '0', STR_PAD_LEFT);
        }

        if (empty($data['date'])) {
            $data['date'] = now()->toDateString();
        }

        if (empty($data['status'])) {
            $data['status'] = 'pending';
        }

        $data['created_by'] = $request->user()?->id;

        $waiver = ProcurementWaiver::create($data);
        $result = $waiver->load($this->with)->toArray();
        $result['amount'] = (float) $waiver->total_amount;
        $result['pr_id'] = $waiver->purchase_request_id;
        $result['pr_number'] = $waiver->purchaseRequest?->pr_number;

        return response()->json([
            'success' => true,
            'message' => 'Waiver berhasil diajukan.',
            'data' => $result,
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, ProcurementWaiver $waiver): JsonResponse
    {
        $input = $request->all();
        if (isset($input['pr_id']) && !isset($input['purchase_request_id'])) {
            $input['purchase_request_id'] = $input['pr_id'];
        }
        if (isset($input['amount']) && !isset($input['total_amount'])) {
            $input['total_amount'] = $input['amount'];
        }

        $data = validator($input, [
            'waiver_number' => ['sometimes', 'string', 'max:80', 'unique:procurement_waivers,waiver_number,' . $waiver->id],
            'purchase_request_id' => ['nullable', 'integer', 'exists:purchase_requests,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'project_name' => ['nullable', 'string', 'max:200'],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'vendor_name' => ['nullable', 'string', 'max:200'],
            'total_amount' => ['sometimes', 'numeric', 'min:0'],
            'description' => ['sometimes', 'string', 'max:255'],
            'justification' => ['sometimes', 'string'],
            'attachment_name' => ['nullable', 'string', 'max:255'],
            'date' => ['sometimes', 'date'],
            'status' => ['nullable', 'string', 'in:pending,approved,rejected'],
        ])->validate();

        $waiver->update($data);

        $result = $waiver->fresh($this->with)->toArray();
        $result['amount'] = (float) $waiver->total_amount;
        $result['pr_id'] = $waiver->purchase_request_id;
        $result['pr_number'] = $waiver->purchaseRequest?->pr_number;

        return response()->json([
            'success' => true,
            'message' => 'Waiver berhasil diperbarui.',
            'data' => $result,
        ]);
    }

    public function destroy(ProcurementWaiver $waiver): JsonResponse
    {
        $waiver->delete();

        return response()->json([
            'success' => true,
            'message' => 'Waiver berhasil dihapus.',
        ]);
    }

    public function approve(Request $request, ProcurementWaiver $waiver): JsonResponse
    {
        $waiver->update([
            'status' => 'approved',
            'approved_by' => $request->user()?->id,
            'approved_at' => now(),
        ]);

        $result = $waiver->fresh($this->with)->toArray();
        $result['amount'] = (float) $waiver->total_amount;
        $result['pr_id'] = $waiver->purchase_request_id;
        $result['pr_number'] = $waiver->purchaseRequest?->pr_number;

        return response()->json([
            'success' => true,
            'message' => 'Waiver berhasil disetujui (approved).',
            'data' => $result,
        ]);
    }

    public function reject(Request $request, ProcurementWaiver $waiver): JsonResponse
    {
        $waiver->update([
            'status' => 'rejected',
            'notes' => $request->input('notes'),
        ]);

        $result = $waiver->fresh($this->with)->toArray();
        $result['amount'] = (float) $waiver->total_amount;
        $result['pr_id'] = $waiver->purchase_request_id;
        $result['pr_number'] = $waiver->purchaseRequest?->pr_number;

        return response()->json([
            'success' => true,
            'message' => 'Waiver ditolak (rejected).',
            'data' => $result,
        ]);
    }
}
