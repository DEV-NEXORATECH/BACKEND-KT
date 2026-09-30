<?php

namespace App\Http\Requests\Master;

use App\Models\Master\Position;
use Illuminate\Foundation\Http\FormRequest;

class StorePositionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('code') && $this->filled('name')) {
            $this->merge(['code' => strtoupper(substr((string) preg_replace('/[^A-Za-z0-9]+/', '-', $this->input('name')), 0, 45)).'-'.str()->random(4)]);
        }
    }

    public function rules(): array
    {
        return ['code' => 'required|string|max:50|unique:positions,code', 'name' => 'required|string|max:150', 'department_id' => 'nullable|integer|exists:departments,id', 'reports_to_position_id' => 'nullable|integer|exists:positions,id', 'description' => 'nullable|string', 'is_active' => 'boolean'];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $parent = $this->integer('reports_to_position_id');
            if (! $parent) return;
            $seen = [];
            while ($parent && ! in_array($parent, $seen, true)) {
                $seen[] = $parent;
                $parent = Position::query()->whereKey($parent)->value('reports_to_position_id');
            }
            if ($parent) $validator->errors()->add('reports_to_position_id', 'Reporting line membentuk circular hierarchy.');
        });
    }
}
