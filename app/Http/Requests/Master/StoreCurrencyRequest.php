<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class StoreCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:10|unique:currencies,code',
            'name' => 'required|string|max:100',
            'symbol' => 'nullable|string|max:10',
            'decimal_places' => 'integer|min:0|max:6',
            'is_base_currency' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}