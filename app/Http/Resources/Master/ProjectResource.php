<?php

namespace App\Http\Resources\Master;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);
        $data['fiscal_year_ids'] = $this->relationLoaded('fiscalYears')
            ? $this->fiscalYears->pluck('id')->values()->all()
            : [];
        return $data;
    }
}
