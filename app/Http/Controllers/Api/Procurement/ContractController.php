<?php

namespace App\Http\Controllers\Api\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Procurement\Contract;
use App\Services\Rbac\DataScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ContractController extends Controller
{
    private array $with = [
        'fiscalYear:id,year,name',
        'vendor:id,code,name',
        'project:id,code,name',
        'donor:id,code,name',
        'creator:id,name,email',
    ];

    public function index(Request $request): JsonResponse
    {
        $query = Contract::query()->with($this->with);

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('contract_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('vendor_name', 'like', "%{$search}%")
                    ->orWhere('project_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('contract_type', $request->query('type'));
        }

        if ($request->filled('status')) {
            $status = $request->query('status');
            if ($status === 'expiring_soon') {
                $query->where('status', 'active')
                    ->whereNotNull('end_date')
                    ->whereBetween('end_date', [now()->toDateString(), now()->addDays(60)->toDateString()]);
            } else {
                $query->where('status', $status);
            }
        }

        $sort = $request->query('sort', 'newest');
        match ($sort) {
            'oldest' => $query->orderBy('start_date', 'asc')->orderBy('id', 'asc'),
            'ending_soon' => $query->orderBy('end_date', 'asc'),
            'value_high' => $query->orderBy('total_value', 'desc'),
            'value_low' => $query->orderBy('total_value', 'asc'),
            default => $query->orderBy('start_date', 'desc')->orderBy('id', 'desc'),
        };

        $perPage = (int) $request->query('per_page', 100);
        $perPage = $perPage < 1 || $perPage > 200 ? 100 : $perPage;

        $contracts = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Daftar kontrak berhasil dimuat.',
            'data' => $contracts->items(),
            'meta' => [
                'current_page' => $contracts->currentPage(),
                'last_page' => $contracts->lastPage(),
                'per_page' => $contracts->perPage(),
                'total' => $contracts->total(),
            ],
        ]);
    }

    public function show(Contract $contract): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $contract->load($this->with),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'contract_number' => ['nullable', 'string', 'max:80', 'unique:procurement_contracts,contract_number'],
            'contract_type' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:255'],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'vendor_name' => ['nullable', 'string', 'max:200'],
            'vendor_contact' => ['nullable', 'string', 'max:200'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'project_name' => ['nullable', 'string', 'max:200'],
            'donor_id' => ['nullable', 'integer', 'exists:donors,id'],
            'donor_name' => ['nullable', 'string', 'max:200'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'total_value' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'status' => ['nullable', 'string', 'in:draft,active,expiring_soon,expired,terminated'],
            'payment_terms' => ['nullable', 'string'],
            'scope_of_work' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        if (empty($data['contract_number'])) {
            $data['contract_number'] = 'CTR-' . now()->format('Y') . '-' . str_pad((string) (Contract::withTrashed()->count() + 1), 3, '0', STR_PAD_LEFT);
        }

        if (empty($data['status'])) {
            $data['status'] = 'draft';
        }

        $data['created_by'] = $request->user()?->id;

        $contract = Contract::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Kontrak berhasil dibuat.',
            'data' => $contract->load($this->with),
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, Contract $contract): JsonResponse
    {
        $data = $request->validate([
            'contract_number' => ['sometimes', 'string', 'max:80', 'unique:procurement_contracts,contract_number,' . $contract->id],
            'contract_type' => ['sometimes', 'string', 'max:50'],
            'title' => ['sometimes', 'string', 'max:255'],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'vendor_name' => ['nullable', 'string', 'max:200'],
            'vendor_contact' => ['nullable', 'string', 'max:200'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'project_name' => ['nullable', 'string', 'max:200'],
            'donor_id' => ['nullable', 'integer', 'exists:donors,id'],
            'donor_name' => ['nullable', 'string', 'max:200'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
            'total_value' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'status' => ['nullable', 'string', 'in:draft,active,expiring_soon,expired,terminated'],
            'payment_terms' => ['nullable', 'string'],
            'scope_of_work' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $contract->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Kontrak berhasil diperbarui.',
            'data' => $contract->fresh($this->with),
        ]);
    }

    public function destroy(Contract $contract): JsonResponse
    {
        $contract->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kontrak berhasil dihapus.',
        ]);
    }

    public function activate(Contract $contract): JsonResponse
    {
        $contract->update(['status' => 'active']);

        return response()->json([
            'success' => true,
            'message' => 'Kontrak berhasil diaktifkan.',
            'data' => $contract->fresh($this->with),
        ]);
    }

    public function terminate(Contract $contract): JsonResponse
    {
        $contract->update(['status' => 'terminated']);

        return response()->json([
            'success' => true,
            'message' => 'Kontrak berhasil dihentikan (terminated).',
            'data' => $contract->fresh($this->with),
        ]);
    }
}
