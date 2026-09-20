<?php

namespace App\Http\Resources\Master;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApprovalMatrixResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $min = (float) $this->min_amount;
        $max = $this->max_amount !== null ? (float) $this->max_amount : null;

        $rangeDisplay = $max !== null
            ? 'Rp ' . number_format($min, 0, ',', '.') . ' - Rp ' . number_format($max, 0, ',', '.')
            : '> Rp ' . number_format($min, 0, ',', '.');

        $roleName = $this->role?->name ?? $this->approver_title ?? 'Approver';
        $scopeParts = [];
        if ($this->project) {
            $scopeParts[] = 'Project: ' . ($this->project->code ?: $this->project->name);
        }
        if ($this->donor) {
            $scopeParts[] = 'Donor: ' . ($this->donor->code ?: $this->donor->name);
        }
        if ($this->department) {
            $scopeParts[] = 'Dept: ' . ($this->department->code ?: $this->department->name);
        }
        $scopeDisplay = !empty($scopeParts) ? implode(' | ', $scopeParts) : 'All Projects & Donors';

        return [
            'id' => $this->id,
            'module' => $this->module,
            'module_display' => ucfirst(str_replace('_', ' ', $this->module)),
            'level' => $this->level,
            'level_display' => 'Level ' . $this->level,
            'min_amount' => $this->min_amount,
            'max_amount' => $this->max_amount,
            'range_display' => $rangeDisplay,
            'role_id' => $this->role_id,
            'role_name' => $this->role?->name,
            'approver_title' => $this->approver_title,
            'approver_display' => $this->approver_title ?: ($this->role?->name ?: 'Approver'),
            'project_id' => $this->project_id,
            'project_name' => $this->project?->name,
            'donor_id' => $this->donor_id,
            'donor_name' => $this->donor?->name,
            'department_id' => $this->department_id,
            'department_name' => $this->department?->name,
            'scope_display' => $scopeDisplay,
            'is_conditional_project_manager' => (bool) $this->is_conditional_project_manager,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'code' => 'LVL-' . $this->level . ' (' . strtoupper($this->module) . ')',
            'name' => $rangeDisplay . ' → ' . ($this->approver_title ?: $roleName),
        ];
    }
}