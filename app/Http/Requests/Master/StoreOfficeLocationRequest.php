<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class StoreOfficeLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => 'nullable|integer|exists:organizations,id',
            'code' => 'required|string|max:30|unique:office_locations,code',
            'name' => 'required|string|max:150',
            'address' => 'nullable|string',
            'pic_name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:100',
            'is_head_office' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}