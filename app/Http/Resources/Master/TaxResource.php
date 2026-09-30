<?php

namespace App\Http\Resources\Master;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);
        $rate = (float) ($this->rate_percent ?? 0);
        $rule = trim((string) ($this->applicable_rule ?? ''));
        if ($rate === 0.0 && $rule !== '') {
            $data['rate_display'] = str_contains(strtolower($rule), 'ter')
                ? 'TER (lihat tabel kalkulator)'
                : ucwords(str_replace('_', ' ', $rule));
        } else {
            $data['rate_display'] = number_format($rate, 2, '.', '').'%';
        }
        return $data;
    }
}
