<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => 'nullable|integer|exists:organizations,id',
            'code' => 'required|string|max:30|unique:taxes,code,' . $this->route('id') . ',id',
            'name' => 'required|string|max:100',
            'tax_type' => 'required|string|max:50',
            'rate_percent' => 'required|numeric|min:0|max:100',
            'effective_start_date' => 'nullable|date',
            'effective_end_date' => 'nullable|date|after_or_equal:effective_start_date',
            'applicable_rule' => 'nullable|string|max:150',
            'description' => 'nullable|string|max:1000',
            'sales_gl_account_id' => 'nullable|integer|exists:chart_of_accounts,id',
            'purchase_gl_account_id' => 'nullable|integer|exists:chart_of_accounts,id',
            'is_active' => 'boolean',
        ];
    }
}
