<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => 'nullable|integer|exists:organizations,id',
            'parent_id' => 'nullable|integer|exists:departments,id',
            'code' => 'required|string|max:30|unique:departments,code',
            'name' => 'required|string|max:150',
            'manager_name' => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ];
    }
}