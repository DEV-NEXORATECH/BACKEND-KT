<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class StoreDonorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:30|unique:donors,code',
            'name' => 'required|string|max:150',
            'type' => 'required|string|max:50',
            'country' => 'nullable|string|max:100',
            'contact_person' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:100',
            'phone' => 'nullable|string|max:30',
            'default_currency_id' => 'nullable|integer|exists:currencies,id',
            'is_active' => 'boolean',
        ];
    }
}