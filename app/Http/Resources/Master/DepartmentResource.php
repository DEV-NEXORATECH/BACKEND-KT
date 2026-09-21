<?php

namespace App\Http\Resources\Master;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);
        $countRelations = [
            'children',
            'cost_centers',
            'employees',
            'approval_matrices',
            'journal_lines',
            'purchase_requests',
            'expense_requests',
            'timesheet_entries',
        ];

        $data['related_records_count'] = collect($countRelations)
            ->sum(fn (string $relation) => (int) ($data["{$relation}_count"] ?? 0));

        foreach ($countRelations as $relation) {
            unset($data["{$relation}_count"]);
        }

        return $data;
    }
}
