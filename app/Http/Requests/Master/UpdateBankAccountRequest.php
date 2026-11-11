<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBankAccountRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('opening_balance')) {
            $value = preg_replace('/[^0-9,.-]/', '', (string) $this->input('opening_balance'));
            if (str_contains($value, ',')) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            }
            $this->merge(['opening_balance' => $value]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fiscal_year_id' => 'nullable|integer|exists:fiscal_years,id',
            'organization_id' => 'nullable|integer|exists:organizations,id',
            'bank_name' => 'required|string|max:100',
            'account_number' => 'required|string|max:50|unique:bank_accounts,account_number,' . $this->route('id') . ',id',
            'account_name' => 'required|string|max:150',
            'swift_code' => 'nullable|string|max:30',
            'description' => 'nullable|string|max:255',
            'currency_id' => 'required|integer|exists:currencies,id',
            'gl_account_id' => 'nullable|integer|exists:chart_of_accounts,id',
            'opening_balance' => 'nullable|numeric',
            'opening_balance_date' => 'nullable|date',
            'is_active' => 'boolean',
        ];
    }
}
