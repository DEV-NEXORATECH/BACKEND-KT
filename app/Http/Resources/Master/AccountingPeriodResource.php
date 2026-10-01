<?php

namespace App\Http\Resources\Master;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountingPeriodResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fiscal_year_id' => $this->fiscal_year_id,
            'period_number' => $this->period_number,
            'name' => $this->name,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'status' => $this->status,
            'is_active' => (bool) $this->is_active,
            'fiscal_year' => $this->whenLoaded('fiscalYear', function () {
                return [
                    'id' => $this->fiscalYear->id,
                    'year' => $this->fiscalYear->year,
                    'name' => $this->fiscalYear->name,
                ];
            }),
        ];
    }
}
