<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExchangeRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => 'required|date',
            'from_currency_id' => 'required|integer|exists:currencies,id',
            'to_currency_id' => 'required|integer|exists:currencies,id',
            'rate' => 'required|numeric|min:0',
            'source' => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ];
    }
}