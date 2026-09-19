<?php

namespace App\Models\Master;

use App\Traits\AuditTrailTrait;
use App\Traits\FilterableSearchableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OfficeLocation extends Model
{
    use HasFactory, SoftDeletes, FilterableSearchableTrait, AuditTrailTrait;

    protected $table = 'office_locations';

    protected $fillable = ['organization_id', 'code', 'name', 'address', 'pic_name', 'phone', 'email', 'is_head_office', 'is_active', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function organization()
    {
        return $this->belongsTo(\App\Models\Master\Organization::class, 'organization_id');
    }

    public function updatedByUser()
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by');
    }

    public function employees()
    {
        return $this->hasMany(\App\Models\Master\Employee::class);
    }

    public function getRelatedRecordsCountAttribute(): int
    {
        return (int) $this->employees()->count();
    }
}