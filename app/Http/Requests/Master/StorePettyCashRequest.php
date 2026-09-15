<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class StorePettyCashRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => 'nullable|integer|exists:organizations,id',
            'office_location_id' => 'nullable|integer|exists:office_locations,id',
            'code' => 'required|string|max:30|unique:petty_cashes,code',
            'name' => 'required|string|max:150',
            'custodian_name' => 'nullable|string|max:100',
            'currency_id' => 'required|integer|exists:currencies,id',
            'limit_amount' => 'required|numeric|min:0',
            'gl_account_id' => 'nullable|integer|exists:chart_of_accounts,id',
            'is_active' => 'boolean',
        ];
    }
}