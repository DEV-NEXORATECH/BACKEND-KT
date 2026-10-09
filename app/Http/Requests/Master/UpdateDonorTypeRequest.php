<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDonorTypeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('id');
        return [
            'code' => ['required', 'string', 'max:30', 'unique:donor_types,code,'.$id],
            'name' => ['required', 'string', 'max:100', 'unique:donor_types,name,'.$id],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }
}
