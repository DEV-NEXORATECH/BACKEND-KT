<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:30|unique:projects,code,' . $this->route('id') . ',id',
            'program_id' => 'nullable|integer|exists:programs,id',
            'grant_agreement_id' => 'nullable|integer|exists:grant_agreements,id',
            'bank_account_id' => 'nullable|integer|exists:bank_accounts,id',
            'name' => 'required|string|max:150',
            'manager_name' => 'nullable|string|max:100',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'budget_currency_id' => 'nullable|integer|exists:currencies,id',
            'total_budget' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $bank = $this->input('bank_account_id') ? \App\Models\Master\BankAccount::find($this->input('bank_account_id')) : null;
            $currencyId = $this->input('budget_currency_id');
            if ($bank && $currencyId && (int) $bank->currency_id !== (int) $currencyId) {
                $validator->errors()->add('bank_account_id', 'Currency rekening project harus sama dengan budget currency project.');
            }
        });
    }
}
