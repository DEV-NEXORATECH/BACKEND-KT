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
        if ($this->relationLoaded('glAccount') && $this->glAccount) {
            $data['gl_account'] = [
                'id' => $this->glAccount->id,
                'code' => $this->glAccount->code,
                'name' => $this->glAccount->name,
                'normal_balance' => $this->glAccount->normal_balance,
            ];
        }

        if ($this->relationLoaded('projectCoaMappings')) {
            $data['project_coa_mappings'] = $this->projectCoaMappings->map(function ($mapping) {
                return [
                    'id' => $mapping->id,
                    'bank_account_id' => $mapping->bank_account_id,
                    'project_id' => $mapping->project_id,
                    'gl_account_id' => $mapping->gl_account_id,
                    'fiscal_year_id' => $mapping->fiscal_year_id,
                    'relation_no' => $mapping->relation_no ?: ($mapping->project?->relation_no ?: $mapping->project?->grantAgreement?->relation_no),
                    'account_name' => $mapping->account_name,
                    'start_date' => $mapping->start_date?->format('Y-m-d'),
                    'end_date' => $mapping->end_date?->format('Y-m-d'),
                    'opening_balance' => (float) ($mapping->opening_balance ?? 0),
                    'notes' => $mapping->notes,
                    'is_active' => (bool) $mapping->is_active,
                    'project' => $mapping->project ? [
                        'id' => $mapping->project->id,
                        'code' => $mapping->project->code,
                        'name' => $mapping->project->name,
                    ] : null,
                    'gl_account' => $mapping->glAccount ? [
                        'id' => $mapping->glAccount->id,
                        'code' => $mapping->glAccount->code,
                        'name' => $mapping->glAccount->name,
                        'normal_balance' => $mapping->glAccount->normal_balance,
                    ] : null,
                ];
            });
        }

        return $data;
    }
}
