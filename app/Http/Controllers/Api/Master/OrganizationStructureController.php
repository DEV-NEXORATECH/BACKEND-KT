<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\Organization;
use Illuminate\Http\JsonResponse;

class OrganizationStructureController extends Controller
{
    public function index(): JsonResponse
    {
        $organizations = Organization::query()->where('is_active', true)->with([
            'departments' => fn ($query) => $query->where('is_active', true)->with([
                'children' => fn ($child) => $child->where('is_active', true),
                'employees' => fn ($employee) => $employee->where('is_active', true)->with('positionMaster.reportsTo'),
            ]),
        ])->get();

        return response()->json(['success' => true, 'data' => $organizations]);
    }
}
