<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseClassRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        $id = $this->route('id');
        return [
            'donor_id' => 'nullable|integer|exists:donors,id',
            'code' => 'required|string|max:40|unique:expense_classes,code,'.$id,
            'name' => 'required|string|max:150',
            'detail_type' => 'required|in:sub_output,workstream,cost_category',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }
}
