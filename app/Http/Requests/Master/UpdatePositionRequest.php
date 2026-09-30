<?php

namespace App\Http\Requests\Master;

use App\Models\Master\Position;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePositionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('code')) {
            $existing = Position::query()->find($this->route('id'));
            if ($existing) $this->merge(['code' => $existing->code]);
        }
    }

    public function rules(): array
    {
        return ['code' => 'required|string|max:50|unique:positions,code,'.$this->route('id').',id', 'name' => 'required|string|max:150', 'department_id' => 'nullable|integer|exists:departments,id', 'reports_to_position_id' => 'nullable|integer|exists:positions,id', 'description' => 'nullable|string', 'is_active' => 'boolean'];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $id = (int) $this->route('id');
            $parent = $this->integer('reports_to_position_id');
            if (! $parent) return;
            if ($parent === $id) { $validator->errors()->add('reports_to_position_id', 'Position tidak boleh melapor ke dirinya sendiri.'); return; }
            $seen = [$id];
            while ($parent && ! in_array($parent, $seen, true)) {
                $seen[] = $parent;
                $parent = Position::query()->whereKey($parent)->value('reports_to_position_id');
            }
            if ($parent) $validator->errors()->add('reports_to_position_id', 'Reporting line membentuk circular hierarchy.');
        });
    }
}
