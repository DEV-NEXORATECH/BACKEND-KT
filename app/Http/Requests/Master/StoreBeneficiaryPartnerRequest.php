<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBeneficiaryPartnerRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('code')) {
            $code = strtoupper(trim((string) $this->input('code')));
            if (preg_match('/^IMP\s*-?\s*(\d+)$/', $code, $matches)) {
                $code = 'IMP - ' . str_pad($matches[1], 3, '0', STR_PAD_LEFT);
            }
            $this->merge(['code' => $code]);
        }
        if ($this->filled('contact_person') && ! $this->filled('pic_name')) {
            $this->merge(['pic_name' => $this->input('contact_person')]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('beneficiary_partners', 'code')->whereNull('deleted_at')],
            'name' => 'required|string|max:150',
            'type' => 'required|string|max:50',
            'address' => 'nullable|string',
            'pic_name' => 'nullable|string|max:100',
            'contact_person' => 'nullable|string|max:100',
            'contact_info' => 'nullable|string|max:150',
            'bank_info' => 'nullable|string|max:200',
            'is_active' => 'boolean',
        ];
    }
}
