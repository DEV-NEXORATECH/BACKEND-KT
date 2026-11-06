<?php

namespace App\Http\Resources\Master;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseClassResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'donor_id' => $this->donor_id,
            'donor' => $this->donor?->only(['id', 'code', 'name']),
            'code' => $this->code,
            'name' => $this->name,
            'detail_type' => $this->detail_type,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
