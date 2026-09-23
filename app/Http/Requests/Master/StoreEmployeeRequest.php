<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id_number' => 'required|string|max:50|unique:employees,employee_id_number',
            'name' => 'required|string|max:150',
            'email' => 'nullable|email|max:100',
            'department_id' => 'nullable|integer|exists:departments,id',
            'office_location_id' => 'nullable|integer|exists:office_locations,id',
            'position' => 'nullable|string|max:100',
            'hourly_cost_rate' => 'nullable|numeric|min:0',
            'bank_name' => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:50',
            'bank_account_holder' => 'nullable|string|max:150',
            'project_bank_name' => 'nullable|string|max:100',
            'project_bank_account_number' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ];
    }
}
