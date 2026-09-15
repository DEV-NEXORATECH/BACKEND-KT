<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:30|unique:payment_methods,code',
            'name' => 'required|string|max:100',
            'type' => 'required|string|max:50',
            'default_gl_account_id' => 'nullable|integer|exists:chart_of_accounts,id',
            'is_active' => 'boolean',
        ];
    }
}