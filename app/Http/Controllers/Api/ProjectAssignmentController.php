<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProjectAssignment;
use Illuminate\Http\Request;

class ProjectAssignmentController extends Controller
{
    public function users()
    {
        return response()->json(['success' => true, 'data' => \App\Models\User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email', 'employee_id'])]);
    }

    public function index(Request $request)
    {
        $rows = ProjectAssignment::query()
            ->with(['user:id,name,email,employee_id', 'project:id,code,name,program_id'])
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->integer('project_id')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->where('is_active', true)
            ->latest('id')
            ->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'role' => ['nullable', 'string', 'max:60'],
            'assigned_from' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'date', 'after_or_equal:assigned_from'],
        ]);

        $row = ProjectAssignment::updateOrCreate(
            ['user_id' => $data['user_id'], 'project_id' => $data['project_id']],
            [...$data, 'is_active' => true, 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id],
        );

        return response()->json(['success' => true, 'data' => $row->load(['user:id,name,email,employee_id', 'project:id,code,name,program_id'])], 201);
    }

    public function destroy(Request $request, ProjectAssignment $projectAssignment)
    {
        $projectAssignment->update(['is_active' => false, 'updated_by' => $request->user()->id]);
        return response()->json(['success' => true]);
    }
}
