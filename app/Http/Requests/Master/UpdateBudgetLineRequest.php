<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use App\Models\Master\ChartOfAccount;
use App\Models\Master\BudgetCategory;
use App\Models\Master\Activity;

class UpdateBudgetLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fiscal_year_id' => 'nullable|integer|exists:fiscal_years,id',
            'grant_agreement_id' => 'nullable|integer|exists:grant_agreements,id',
            'project_id' => 'nullable|integer|exists:projects,id',
            'activity_id' => 'nullable|integer|exists:activities,id',
<<<<<<< HEAD
            'sub_output_code' => 'nullable|string|max:80',
            'budget_category_id' => 'required|integer|exists:budget_categories,id',
=======
            'activity_code' => 'nullable|string|max:50',
            'activity_name' => 'nullable|string|max:255',
            'sub_output_code' => 'nullable|string|max:50',
            'sub_output_name' => 'nullable|string|max:255',
            'budget_category_id' => 'nullable|integer|exists:budget_categories,id',
>>>>>>> f43874195204991ff2ac8f360cd50738da12243e
            'line_code' => 'required|string|max:50',
            'description' => 'required|string|max:255',
            'proposal_period' => 'nullable|in:monthly,quarterly,semester,annual',
            'allocation_type' => 'nullable|in:annual,quarterly,monthly',
            'period_allocations' => 'nullable|array',
            'currency_id' => 'nullable|integer|exists:currencies,id',
            'exchange_rate' => 'nullable|numeric|min:0.000001',
            'unit_of_measure_id' => 'nullable|integer|exists:unit_of_measures,id',
            'unit_price' => 'required|numeric|min:0',
            'quantity' => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'base_amount' => 'nullable|numeric|min:0',
            'period_allocations' => 'nullable|array',
            'period_allocations.*.period' => 'required_with:period_allocations|string|max:20',
            'period_allocations.*.amount' => 'required_with:period_allocations|numeric|min:0',
            'gl_account_id' => 'nullable|integer|exists:chart_of_accounts,id',
            'cost_type' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ];
    }

    protected function prepareForValidation(): void
    {
        $mapping = ['BCAT-100' => '5100', 'BCAT-200' => '5200', 'BCAT-300' => '5300'];
        $categoryCode = BudgetCategory::query()->whereKey($this->input('budget_category_id'))->value('code');
        $coaCode = $mapping[$categoryCode] ?? null;
        if (! $this->filled('gl_account_id') && $coaCode) {
            $coaId = ChartOfAccount::query()->where('code', $coaCode)->where('account_type', 'expense')->value('id');
            if ($coaId) {
                $this->merge(['gl_account_id' => $coaId, 'cost_type' => $coaCode]);
            }
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $allocations = $this->input('period_allocations');
            if (is_array($allocations) && count($allocations) > 0) {
                $sum = collect($allocations)->sum(fn ($row) => (float) ($row['amount'] ?? 0));
                if (abs($sum - (float) $this->input('total_amount', 0)) > 0.01) {
                    $validator->errors()->add('period_allocations', 'Period allocations must equal the total budget amount.');
                }
            }
            if ($this->filled('gl_account_id')) {
                $account = ChartOfAccount::query()->find($this->integer('gl_account_id'));
                if ($account && strtolower((string) $account->account_type) !== 'expense') {
                    $validator->errors()->add('gl_account_id', 'Budget lines can only use an expense CoA.');
                }
            }
            if ($this->filled('activity_id') && $this->filled('project_id')) {
                $activityBelongsToProject = Activity::query()->whereKey($this->integer('activity_id'))->where('project_id', $this->integer('project_id'))->exists();
                if (! $activityBelongsToProject) {
                    $validator->errors()->add('activity_id', 'The selected Activity must belong to the selected Project.');
                }
            }
        });
    }
}
