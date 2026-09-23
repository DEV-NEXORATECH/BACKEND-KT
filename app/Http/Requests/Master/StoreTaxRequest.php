<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:30|unique:taxes,code',
            'name' => 'required|string|max:100',
            'tax_type' => 'required|string|max:50',
            'rate_percent' => 'required|numeric|min:0|max:100',
            'description' => 'nullable|string|max:1000',
            'sales_gl_account_id' => 'nullable|integer|exists:chart_of_accounts,id',
            'purchase_gl_account_id' => 'nullable|integer|exists:chart_of_accounts,id',
            'is_active' => 'boolean',
        ];
    }
}