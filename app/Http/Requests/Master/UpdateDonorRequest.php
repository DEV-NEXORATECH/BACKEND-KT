<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Master\DonorType;

class UpdateDonorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('type') && $this->filled('donor_type_id')) {
            $this->merge(['type' => DonorType::find($this->input('donor_type_id'))?->name]);
        }
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:30|unique:donors,code,' . $this->route('id') . ',id',
            'name' => 'required|string|max:150',
            'type' => 'required|string|max:50',
            'donor_type_id' => 'required|integer|exists:donor_types,id',
            'country' => 'nullable|string|max:100',
            'contact_person' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:100',
            'phone' => 'nullable|string|max:30',
            'default_currency_id' => 'nullable|integer|exists:currencies,id',
            'fiscal_year_id' => 'nullable|integer|exists:fiscal_years,id',
            'status' => 'nullable|in:active,terminated,completed,inactive',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
