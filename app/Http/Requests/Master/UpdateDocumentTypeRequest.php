<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:30|unique:document_types,code,' . $this->route('id') . ',id',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'is_mandatory_for_payout' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}