<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parent_id' => 'nullable|integer|exists:expense_categories,id',
            'code' => 'required|string|max:30|unique:expense_categories,code,' . $this->route('id') . ',id',
            'name' => 'required|string|max:150',
            'default_gl_account_id' => 'nullable|integer|exists:chart_of_accounts,id',
            'is_taxable' => 'boolean',
            'requires_receipt' => 'boolean',
            'requires_advance_settlement' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}