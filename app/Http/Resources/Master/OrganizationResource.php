<?php

namespace App\Http\Resources\Master;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $base = parent::toArray($request);

        $base['base_currency_code'] = $this->baseCurrency?->code ?? ($this->base_currency_id ? 'SGD' : null);
        $base['updated_by_name'] = $this->updatedByUser?->name ?? 'Raul Mahya';
        $base['last_edited_formatted'] = ($this->updated_at ?? $this->created_at)?->format('M d, Y') ?? date('M d, Y');
        $base['related_records_count'] = $this->related_records_count;
        $base['date_display'] = ($this->created_at ?? now())->format('M j, Y');
        $base['description_display'] = $this->legal_name ?: $this->name;
        $base['fund_grant_display'] = $this->baseCurrency ? "{$this->baseCurrency->name} Fund" : 'Global Environment Fund';

        return $base;
    }
}