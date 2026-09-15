<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class StoreCostCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => 'nullable|integer|exists:organizations,id',
            'department_id' => 'nullable|integer|exists:departments,id',
            'code' => 'required|string|max:30|unique:cost_centers,code',
            'name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }
}