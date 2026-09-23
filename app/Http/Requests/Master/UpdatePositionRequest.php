<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePositionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return ['code' => 'required|string|max:50|unique:positions,code,'.$this->route('id').',id', 'name' => 'required|string|max:150', 'department_id' => 'nullable|integer|exists:departments,id', 'description' => 'nullable|string', 'is_active' => 'boolean'];
    }
}
