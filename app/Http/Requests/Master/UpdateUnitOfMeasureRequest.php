<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUnitOfMeasureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:20|unique:unit_of_measures,code,' . $this->route('id') . ',id',
            'name' => 'required|string|max:100',
            'category' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ];
    }
}