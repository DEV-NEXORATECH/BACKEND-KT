<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Requests\Master\StoreApprovalMatrixRequest;
use App\Http\Requests\Master\UpdateApprovalMatrixRequest;
use App\Http\Resources\Master\ApprovalMatrixResource;
use App\Models\Master\ApprovalMatrix;
use App\Models\User;
use App\Services\Approval\ApprovalIdentityResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ApprovalMatrixController extends BaseMasterController
{
    protected string $modelClass = ApprovalMatrix::class;
    protected string $resourceClass = ApprovalMatrixResource::class;
    protected string $storeRequestClass = StoreApprovalMatrixRequest::class;
    protected string $updateRequestClass = UpdateApprovalMatrixRequest::class;
    protected array $searchableColumns = ['module', 'approver_title', 'description'];
    protected array $defaultWith = ['fiscalYear', 'role', 'user', 'employee', 'project', 'donor', 'department'];

    public function index(Request $request): JsonResponse
    {
        $query = ApprovalMatrix::query()
            ->withoutGlobalScope('fiscal_year_context')
            ->with($this->defaultWith);

        if ($request->filled('fiscal_year_id')) {
            $rawYear = $request->query('fiscal_year_id');
            if ($rawYear === 'global') {
                $query->whereNull('fiscal_year_id');
            } elseif ($request->boolean('exact_fiscal_year')) {
                $query->where('fiscal_year_id', (int) $rawYear);
            } else {
                // Global rules (fiscal_year_id NULL) apply to every year, so show them alongside year-specific rules.
                $query->where(function ($scope) use ($rawYear) {
                    $scope->whereNull('fiscal_year_id')->orWhere('fiscal_year_id', (int) $rawYear);
                });
            }
        }

        // Only filter active rows when explicitly requested or when requested as a select dropdown (`options=1`).
        // The Settings matrix screen passes `paginate=false` and must see both active and inactive rules.
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        } elseif ($request->boolean('options')) {
            $query->where('is_active', true);
        }

        if (Schema::hasColumn((new ApprovalMatrix)->getTable(), 'deleted_at')) {
            $query->whereNull('approval_matrices.deleted_at');
        }

        if (method_exists(ApprovalMatrix::class, 'scopeApplyFilters')) {
            $query->applyFilters($request, $this->searchableColumns);
        }

        $query->orderBy('module')->orderBy('level')->orderBy('min_amount')->orderBy('id');

        if ($request->boolean('options') || $request->query('paginate') === 'false') {
            $items = $query->get();

            return $this->successResponse(
                ApprovalMatrixResource::collection($items),
                'Data berhasil dimuat.'
            );
        }

        $perPage = (int) $request->query('per_page', 15);
        if ($perPage > 100 || $perPage < 1) {
            $perPage = 15;
        }
        $paginated = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil dimuat.',
            'data' => ApprovalMatrixResource::collection($paginated->items()),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
        ], Response::HTTP_OK);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = app($this->storeRequestClass)->validated();
        $this->validateIdentityConsistency($validated);

        $explicitGlobalYear = $request->exists('fiscal_year_id')
            && ($request->input('fiscal_year_id') === null || $request->input('fiscal_year_id') === '');

        $record = ApprovalMatrix::create($validated);
        if ($explicitGlobalYear && $record->fiscal_year_id !== null) {
            // Bypass FiscalYearScopedTrait::creating defaulting when the caller explicitly chose All Fiscal Years (Global).
            $record->forceFill(['fiscal_year_id' => null])->saveQuietly();
        }

        $record->loadMissing($this->defaultWith);

        return $this->successResponse(
            new ApprovalMatrixResource($record),
            'Data berhasil ditambahkan.',
            Response::HTTP_CREATED
        );
    }

    public function update(Request $request, $id): JsonResponse
    {
        $record = ApprovalMatrix::query()
            ->withoutGlobalScope('fiscal_year_context')
            ->findOrFail($id);

        $validated = app($this->updateRequestClass)->validated();
        $this->validateIdentityConsistency($validated);

        if ($request->exists('fiscal_year_id') && ($request->input('fiscal_year_id') === null || $request->input('fiscal_year_id') === '')) {
            $validated['fiscal_year_id'] = null;
        }

        $record->update($validated);
        $record->loadMissing($this->defaultWith);

        return $this->successResponse(
            new ApprovalMatrixResource($record),
            'Data berhasil diperbarui.'
        );
    }

    public function approvers(Request $request): JsonResponse
    {
        $module = $request->query('module');
        $users = User::query()
            ->with(['role.permissions:id,slug', 'employee:id,employee_id_number,name,position'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role_id', 'employee_id', 'is_active'])
            ->map(function (User $user) use ($module) {
                $resolvedEmployeeId = app(ApprovalIdentityResolver::class)->employeeId($user);
                $canPostJournal = $user->hasPermission('accounting.journal.post');

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role_id' => $user->role_id,
                    'role' => $user->role?->name,
                    'role_slug' => $user->role?->slug,
                    'employee_id' => $resolvedEmployeeId,
                    'employee_name' => $user->employee?->name,
                    'employee_position' => $user->employee?->position,
                    'can_approve_journal' => $canPostJournal,
                    'eligible_for_module' => $module === 'journal' ? $canPostJournal : true,
                ];
            })
            ->values();

        return $this->successResponse($users, 'Daftar user approver berhasil dimuat.');
    }

    private function validateIdentityConsistency(array $validated): void
    {
        $userId = ! empty($validated['user_id']) ? (int) $validated['user_id'] : null;
        $employeeId = ! empty($validated['employee_id']) ? (int) $validated['employee_id'] : null;

        if (! $userId || ! $employeeId) {
            return;
        }

        $user = User::query()->find($userId);
        if (! $user) {
            return;
        }

        $resolvedEmployeeId = app(ApprovalIdentityResolver::class)->employeeId($user);
        if ($resolvedEmployeeId !== null && $resolvedEmployeeId !== $employeeId) {
            throw ValidationException::withMessages([
                'user_id' => 'User login dan Employee yang dipilih merujuk ke orang yang berbeda. Kosongkan salah satu atau pilih akun yang sesuai.',
            ]);
        }
    }
}

