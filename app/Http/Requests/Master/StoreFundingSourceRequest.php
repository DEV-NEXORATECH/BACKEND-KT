<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class StoreFundingSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:30|unique:funding_sources,code',
            'name' => 'required|string|max:150',
            'funding_type' => 'required|string|max:50',
            'restriction_type' => 'required|in:unrestricted,temporarily_restricted,permanently_restricted',
            'is_active' => 'boolean',
        ];
    }
}