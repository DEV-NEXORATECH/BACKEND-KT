<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class StoreExchangeRateRequest extends FormRequest
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
            'rate_type' => 'required|in:spot,average,donor,bank,manual,donor_incoming_avg',
            'source' => 'nullable|string|max:150',
            'reference' => 'nullable|string|max:150',
            'notes' => 'nullable|string|max:2000',
            'is_active' => 'boolean',
        ];
    }
}
