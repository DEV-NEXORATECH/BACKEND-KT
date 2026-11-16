<?php

namespace App\Http\Resources\Master;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BudgetLineResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);
        $bank = $this->project?->bankAccount
            ?: $this->grantAgreement?->bankAccount
            ?: $this->project?->grantAgreement?->bankAccount;
        $bankGlAccountId = $bank?->resolveGlAccountId($this->project_id, null, $this->fiscal_year_id);
        $bankGlAccount = $bankGlAccountId ? \App\Models\Master\ChartOfAccount::find($bankGlAccountId) : null;

        $data['bank_account'] = $bank ? [
            'id' => $bank->id,
            'bank_name' => $bank->bank_name,
            'account_number' => $bank->account_number,
            'account_name' => $bank->account_name,
            'gl_account_id' => $bankGlAccountId,
            'is_active' => (bool) $bank->is_active,
        ] : null;
        $data['bank_gl_account'] = $bankGlAccount ? [
            'id' => $bankGlAccount->id,
            'code' => $bankGlAccount->code,
            'name' => $bankGlAccount->name,
            'account_type' => $bankGlAccount->account_type,
        ] : null;
        $data['bank_link_status'] = ! $bank
            ? 'missing_bank_account'
            : (! $bankGlAccountId ? 'missing_bank_coa' : 'linked');

        return $data;
    }
}
