<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class StoreApprovalMatrixRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'module' => 'required|string|max:50',
            'level' => 'required|integer|min:1',
            'min_amount' => 'required|numeric|min:0',
            'max_amount' => 'nullable|numeric|gte:min_amount',
            'role_id' => 'nullable|integer|exists:roles,id',
            'project_id' => 'nullable|integer|exists:projects,id',
            'donor_id' => 'nullable|integer|exists:donors,id',
            'department_id' => 'nullable|integer|exists:departments,id',
            'approver_title' => 'nullable|string|max:100',
            'is_conditional_project_manager' => 'boolean',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }
}