<?php

namespace App\Http\Resources\Master;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankAccountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);
        // Native HTML date inputs only accept YYYY-MM-DD. Eloquent's default
        // JSON serialization includes a timestamp, which made the edit drawer
        // look empty even though the value was still stored in the database.
        $data['opening_balance_date'] = $this->opening_balance_date?->format('Y-m-d');
        $data['opening_balance_display'] = 'Rp ' . number_format((float) ($this->opening_balance ?? 0), 2, ',', '.');

        return $data;
    }
}
