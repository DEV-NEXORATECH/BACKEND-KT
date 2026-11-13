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

        $data['bank_account'] = $bank ? [
            'id' => $bank->id,
            'bank_name' => $bank->bank_name,
            'account_number' => $bank->account_number,
            'account_name' => $bank->account_name,
            'gl_account_id' => $bank->gl_account_id,
            'is_active' => (bool) $bank->is_active,
        ] : null;
        $data['bank_gl_account'] = $bank?->glAccount ? [
            'id' => $bank->glAccount->id,
            'code' => $bank->glAccount->code,
            'name' => $bank->glAccount->name,
            'account_type' => $bank->glAccount->account_type,
        ] : null;
        $data['bank_link_status'] = ! $bank
            ? 'missing_bank_account'
            : (! $bank->gl_account_id ? 'missing_bank_coa' : 'linked');

        return $data;
    }
}
