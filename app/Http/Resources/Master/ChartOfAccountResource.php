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
        $data['balance'] = round(($this->normal_balance ?? 'debit') === 'credit' ? $credit - $debit : $debit - $credit, 2);
        unset($data['posted_debit_total'], $data['posted_credit_total']);
        return $data;
    }
}
