<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChartOfAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parent_id' => 'nullable|integer|exists:chart_of_accounts,id',
            'code' => 'required|string|max:50|unique:chart_of_accounts,code,' . $this->route('id') . ',id',
            'name' => 'required|string|max:150',
            'account_type' => 'required|in:asset,liability,equity,revenue,expense',
            'normal_balance' => 'required|in:debit,credit',
            'level' => 'integer|min:1',
            'is_header' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}