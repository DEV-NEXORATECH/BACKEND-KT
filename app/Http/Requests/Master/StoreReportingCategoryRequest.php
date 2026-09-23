<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class StoreReportingCategoryRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:30|unique:reporting_categories,code',
            'name' => 'required|string|max:150',
            'category_type' => 'required|string|max:50',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }
}
