<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class StoreDonorTypeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', 'unique:donor_types,code'],
            'name' => ['required', 'string', 'max:100', 'unique:donor_types,name'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }
}
