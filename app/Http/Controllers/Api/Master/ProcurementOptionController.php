<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\ProcurementOption;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class ProcurementOptionController extends Controller
{
    private function type(Request $request): string
    {
        return str_contains($request->path(), 'delivery-methods') ? 'delivery_method' : 'payment_term';
    }

    public function index(Request $request): JsonResponse
    {
        $query = ProcurementOption::query()->where('option_type', $this->type($request))->orderBy('name');
        if ($request->boolean('options') || $request->query('paginate') === 'false') {
            return response()->json(['success' => true, 'data' => $query->where('is_active', true)->get()]);
        }

        $perPage = min(100, max(1, (int) $request->query('per_page', 10)));
        $rows = $query->paginate($perPage);
        return response()->json(['success' => true, 'data' => $rows->items(), 'meta' => [
            'current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'per_page' => $rows->perPage(), 'total' => $rows->total(),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $type = $this->type($request);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('procurement_options', 'code')->where(fn ($q) => $q->where('option_type', $type))],
            'name' => 'required|string|max:100', 'is_active' => 'boolean',
        ]);
        $data['option_type'] = $type;
        $option = ProcurementOption::create($data);
        return response()->json(['success' => true, 'data' => $option], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $procurementOption = ProcurementOption::findOrFail($id);
        abort_unless($procurementOption->option_type === $this->type($request), 404);
        $data = $request->validate(['code' => 'sometimes|string|max:30', 'name' => 'sometimes|string|max:100', 'is_active' => 'boolean']);
        $procurementOption->update($data);
        return response()->json(['success' => true, 'data' => $procurementOption->fresh()]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $procurementOption = ProcurementOption::findOrFail($id);
        abort_unless($procurementOption->option_type === $this->type($request), 404);
        $procurementOption->delete();
        return response()->json(['success' => true]);
    }
}
