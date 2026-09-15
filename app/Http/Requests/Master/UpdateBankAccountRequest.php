<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => 'nullable|integer|exists:organizations,id',
            'bank_name' => 'required|string|max:100',
            'account_number' => 'required|string|max:50|unique:bank_accounts,account_number,' . $this->route('id') . ',id',
            'account_name' => 'required|string|max:150',
            'swift_code' => 'nullable|string|max:30',
            'currency_id' => 'required|integer|exists:currencies,id',
            'gl_account_id' => 'nullable|integer|exists:chart_of_accounts,id',
            'is_active' => 'boolean',
        ];
    }
}