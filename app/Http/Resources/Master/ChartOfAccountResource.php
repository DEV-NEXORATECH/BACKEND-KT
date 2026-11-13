<?php

namespace App\Http\Resources\Master;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChartOfAccountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);
        $debit = (float) ($this->posted_debit_total ?? 0);
        $credit = (float) ($this->posted_credit_total ?? 0);
        $movement = ($this->normal_balance ?? 'debit') === 'credit' ? $credit - $debit : $debit - $credit;
        $data['balance'] = round((float) ($this->opening_balance ?? 0) + $movement, 2);
        $data['bank_balance'] = round((float) ($this->bank_opening_balance ?? 0) + (float) ($this->bank_transaction_movement ?? 0), 2);
        $data['bank_account_count'] = $this->relationLoaded('bankAccounts') ? $this->bankAccounts->count() : 0;
        $data['is_bank_account'] = $data['bank_account_count'] > 0;
        $data['bank_accounts'] = $this->relationLoaded('bankAccounts')
            ? $this->bankAccounts->map(fn ($bank) => [
                'id' => $bank->id,
                'bank_name' => $bank->bank_name,
                'account_number' => $bank->account_number,
                'account_name' => $bank->account_name,
                'is_active' => (bool) $bank->is_active,
                'opening_balance' => (float) ($bank->opening_balance ?? 0),
            ])->values()
            : [];
        unset($data['posted_debit_total'], $data['posted_credit_total'], $data['bank_opening_balance'], $data['bank_transaction_movement']);
        return $data;
    }
}
