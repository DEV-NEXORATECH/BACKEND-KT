<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\Project;
use App\Http\Requests\Master\StoreProjectRequest;
use App\Http\Requests\Master\UpdateProjectRequest;
use App\Http\Resources\Master\ProjectResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProjectController extends BaseMasterController
{
    protected string $modelClass = Project::class;
    protected string $resourceClass = ProjectResource::class;
    protected string $storeRequestClass = StoreProjectRequest::class;
    protected string $updateRequestClass = UpdateProjectRequest::class;
    protected array $searchableColumns = ['code', 'name', 'manager_name'];
    protected array $defaultWith = ['program', 'grantAgreement.donor', 'budgetCurrency', 'bankAccount', 'fiscalYears'];

    public function index(Request $request): JsonResponse
    {
        // Projects use a many-to-many fiscal-year pivot rather than a direct
        // fiscal_year_id column, so handle the options dropdown explicitly.
        if ($request->boolean('options') && $request->filled('fiscal_year_id')) {
            $projects = Project::query()
                ->with($this->defaultWith)
                ->where('is_active', true)
                ->whereHas('fiscalYears', fn ($query) => $query->whereKey($request->integer('fiscal_year_id')))
                ->orderBy('name')
                ->get();

            return $this->successResponse(ProjectResource::collection($projects), 'Daftar opsi berhasil dimuat.');
        }

        return parent::index($request);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = app($this->storeRequestClass)->validated();
        $fiscalYearIds = $validated['fiscal_year_ids'] ?? [];
        if (empty($fiscalYearIds) && !empty($validated['fiscal_year_id'])) {
            $fiscalYearIds = [(int) $validated['fiscal_year_id']];
        }
        unset($validated['fiscal_year_ids'], $validated['fiscal_year_id']);
        $record = Project::create($validated);
        $record->fiscalYears()->sync($fiscalYearIds);
        $record->load($this->defaultWith);
        return response()->json(['success' => true, 'message' => 'Data berhasil ditambahkan.', 'data' => new ProjectResource($record)], Response::HTTP_CREATED);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $validated = app($this->updateRequestClass)->validated();
        $hasFiscalYears = array_key_exists('fiscal_year_ids', $validated) || array_key_exists('fiscal_year_id', $validated);
        $fiscalYearIds = $validated['fiscal_year_ids'] ?? [];
        if (empty($fiscalYearIds) && !empty($validated['fiscal_year_id'])) {
            $fiscalYearIds = [(int) $validated['fiscal_year_id']];
        }
        unset($validated['fiscal_year_ids'], $validated['fiscal_year_id']);
        $record = Project::findOrFail($id);
        $record->update($validated);
        if ($hasFiscalYears) {
            $record->fiscalYears()->sync($fiscalYearIds);
        }
        $record->load($this->defaultWith);
        return response()->json(['success' => true, 'message' => 'Data berhasil diperbarui.', 'data' => new ProjectResource($record)]);
    }
}
