<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProcurementItemRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['code' => 'required|string|max:30|unique:procurement_items,code,'.$this->route('id').',id', 'name' => 'required|string|max:150', 'item_type' => 'required|in:goods,service', 'procurement_category_id' => 'nullable|integer|exists:procurement_categories,id', 'unit_of_measure_id' => 'nullable|integer|exists:unit_of_measures,id', 'default_unit_price' => 'nullable|numeric|min:0', 'description' => 'nullable|string', 'is_active' => 'boolean']; }
}
