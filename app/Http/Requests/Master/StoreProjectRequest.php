<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:30|unique:projects,code',
            'program_id' => 'nullable|integer|exists:programs,id',
            'grant_agreement_id' => 'nullable|integer|exists:grant_agreements,id',
            'name' => 'required|string|max:150',
            'manager_name' => 'nullable|string|max:100',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'budget_currency_id' => 'nullable|integer|exists:currencies,id',
            'total_budget' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ];
    }
}