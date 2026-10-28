<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssetCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:30|unique:asset_categories,code',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'useful_life_months' => 'required|integer|min:0',
            'depreciation_method' => 'required|in:straight_line,declining_balance,none',
            'is_non_depreciable' => 'boolean',
            'residual_value_percent' => 'nullable|numeric|min:0|max:100',
            'asset_gl_account_id' => 'nullable|integer|exists:chart_of_accounts,id',
            'depreciation_gl_account_id' => 'nullable|integer|exists:chart_of_accounts,id',
            'accumulated_gl_account_id' => 'nullable|integer|exists:chart_of_accounts,id',
            'is_active' => 'boolean',
        ];
    }
}
