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
            'min_amount' => 'required|numeric|min:0',
            'max_amount' => 'nullable|numeric|gte:min_amount',
            'level' => 'required|integer|min:1',
            'role_id' => 'nullable|integer|exists:roles,id',
            'is_conditional_project_manager' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}