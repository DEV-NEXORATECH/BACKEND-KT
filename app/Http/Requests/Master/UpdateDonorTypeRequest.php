<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDonorTypeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('id');
        return [
            'fiscal_year_id' => ['nullable', 'integer', 'exists:fiscal_years,id'],
            'code' => ['required', 'string', 'max:30', 'unique:donor_types,code,'.$id],
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('donor_types', 'name')
                    ->ignore($id)
                    ->where(fn ($query) => $query->whereRaw('BINARY name = ?', [$this->input('name')])),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }
}
