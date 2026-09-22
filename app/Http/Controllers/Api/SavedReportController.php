<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SavedReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavedReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => SavedReport::where('user_id', $request->user()->id)->latest('updated_at')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'configuration' => ['required', 'array']]);
        $saved = SavedReport::create(['user_id' => $request->user()->id, ...$data]);
        return response()->json(['success' => true, 'data' => $saved], 201);
    }

    public function destroy(Request $request, SavedReport $savedReport): JsonResponse
    {
        abort_unless((int) $savedReport->user_id === (int) $request->user()->id, 404);
        $savedReport->delete();
        return response()->json(['success' => true]);
    }
}
