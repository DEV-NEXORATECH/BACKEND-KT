<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVendorCategoryRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['code' => 'required|string|max:30|unique:vendor_categories,code,'.$this->route('id').',id', 'name' => 'required|string|max:150', 'description' => 'nullable|string', 'is_active' => 'boolean']; }
}
