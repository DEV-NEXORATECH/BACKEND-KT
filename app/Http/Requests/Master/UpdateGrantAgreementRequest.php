<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGrantAgreementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'grant_no' => 'required|string|max:50|unique:grant_agreements,grant_no,' . $this->route('id') . ',id',
            'donor_id' => 'required|integer|exists:donors,id',
            'funding_source_id' => 'nullable|integer|exists:funding_sources,id',
            'agreement_name' => 'required|string|max:200',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'currency_id' => 'required|integer|exists:currencies,id',
            'grant_value' => 'required|numeric|min:0',
            'exchange_rate_contract' => 'nullable|numeric|min:0',
            'bank_account_id' => 'nullable|integer|exists:bank_accounts,id',
            'status' => 'required|in:draft,active,closed,suspended',
            'is_active' => 'boolean',
        ];
    }
}