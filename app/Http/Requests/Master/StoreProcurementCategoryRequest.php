<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class StoreProcurementCategoryRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['parent_id' => 'nullable|integer|exists:procurement_categories,id', 'code' => 'required|string|max:30|unique:procurement_categories,code', 'name' => 'required|string|max:150', 'description' => 'nullable|string', 'is_active' => 'boolean']; }
}
