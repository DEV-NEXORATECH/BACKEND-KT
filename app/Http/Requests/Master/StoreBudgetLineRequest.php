<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class StoreBudgetLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'grant_agreement_id' => 'nullable|integer|exists:grant_agreements,id',
            'project_id' => 'nullable|integer|exists:projects,id',
            'budget_category_id' => 'required|integer|exists:budget_categories,id',
            'line_code' => 'required|string|max:50',
            'description' => 'required|string|max:255',
            'proposal_period' => 'nullable|in:monthly,quarterly,semester,annual',
            'currency_id' => 'nullable|integer|exists:currencies,id',
            'exchange_rate' => 'nullable|numeric|min:0.000001',
            'unit_of_measure_id' => 'nullable|integer|exists:unit_of_measures,id',
            'unit_price' => 'required|numeric|min:0',
            'quantity' => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'base_amount' => 'nullable|numeric|min:0',
            'gl_account_id' => 'nullable|integer|exists:chart_of_accounts,id',
            'is_active' => 'boolean',
        ];
    }
}
