<?php

namespace App\Http\Requests\Master;

use App\Models\Master\ChartOfAccount;
use Illuminate\Foundation\Http\FormRequest;

class StoreChartOfAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parent_id' => 'nullable|integer|exists:chart_of_accounts,id',
            'account_category_id' => 'nullable|integer|exists:account_categories,id',
            'code' => 'required|string|max:50|unique:chart_of_accounts,code',
            'name' => 'required|string|max:150',
            'fiscal_year_id' => 'nullable|integer|exists:fiscal_years,id',
            'account_type' => 'required|in:asset,liability,equity,revenue,expense,other_income,other_expense',
            'detail_type' => 'required|string|max:120',
            'normal_balance' => 'required|in:debit,credit',
            'opening_balance' => 'nullable|numeric|min:0',
            'opening_balance_date' => 'nullable|date',
            // These are normalized in prepareForValidation from the parent choice.
            'level' => 'integer|in:1,2',
            'is_header' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'level' => $this->filled('parent_id') ? 2 : 1,
            'is_header' => ! $this->filled('parent_id'),
            'normal_balance' => in_array($this->input('account_type'), ['asset', 'expense', 'other_expense'], true) ? 'debit' : 'credit',
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $prefixes = ['asset' => '1', 'liability' => '2', 'equity' => '3', 'revenue' => '4', 'expense' => '5', 'other_income' => '6', 'other_expense' => '7'];
            $type = (string) $this->input('account_type');
            if ($this->filled('code') && isset($prefixes[$type]) && ! str_starts_with((string) $this->input('code'), $prefixes[$type])) {
                $validator->errors()->add('code', "Nomor akun {$type} harus diawali {$prefixes[$type]}.");
            }
            if (! $this->filled('parent_id')) return;
            $parent = ChartOfAccount::find($this->integer('parent_id'));
            if (! $parent?->is_header || (int) $parent->level !== 1) {
                $validator->errors()->add('parent_id', 'Parent Account harus merupakan Header Account level 1.');
            }
        });
    }
}
