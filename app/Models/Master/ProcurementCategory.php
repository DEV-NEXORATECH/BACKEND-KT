<?php

namespace App\Models\Master;

use App\Traits\AuditTrailTrait;
use App\Traits\FilterableSearchableTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcurementCategory extends Model
{
    use SoftDeletes, FilterableSearchableTrait, AuditTrailTrait;

    protected $fillable = ['parent_id', 'code', 'name', 'description', 'is_active', 'created_by', 'updated_by', 'deleted_by'];
    protected $casts = ['is_active' => 'boolean'];

    public function parent() { return $this->belongsTo(self::class, 'parent_id'); }
    public function children() { return $this->hasMany(self::class, 'parent_id'); }
    public function items() { return $this->hasMany(ProcurementItem::class); }
}
