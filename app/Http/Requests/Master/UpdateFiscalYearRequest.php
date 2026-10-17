<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFiscalYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year' => 'required|integer',
            'name' => 'required|string|max:100',
            'calendar_code' => 'nullable|string|max:30|in:JAN_DEC,APR_MAR',
            'start_month' => 'nullable|integer|between:1,12',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'status' => 'required|in:open,closed',
            'is_active' => 'boolean',
        ];
    }

    protected function prepareForValidation(): void
    {
        $calendar = $this->input('calendar_code', 'JAN_DEC');
        $this->merge([
            'calendar_code' => $calendar,
            'start_month' => $this->input('start_month', $calendar === 'APR_MAR' ? 4 : 1),
        ]);
    }
}
