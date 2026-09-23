<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:30|unique:organizations,code,' . $this->route('id') . ',id',
            'name' => 'required|string|max:150',
            'legal_name' => 'nullable|string|max:150',
            'npwp' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:150',
            'website' => 'nullable|url|max:255',
            'pass_code' => 'nullable|string|max:100',
            'address' => 'nullable|string',
            'base_currency_id' => 'nullable|integer',
            'fiscal_year_start_month' => 'nullable|integer|between:1,12',
            'logo_url' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ];
    }
}
