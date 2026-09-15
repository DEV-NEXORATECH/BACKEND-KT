<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBeneficiaryPartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:30|unique:beneficiary_partners,code,' . $this->route('id') . ',id',
            'name' => 'required|string|max:150',
            'type' => 'required|string|max:50',
            'address' => 'nullable|string',
            'pic_name' => 'nullable|string|max:100',
            'contact_info' => 'nullable|string|max:150',
            'bank_info' => 'nullable|string|max:200',
            'is_active' => 'boolean',
        ];
    }
}