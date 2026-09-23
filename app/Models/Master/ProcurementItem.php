<?php

namespace App\Models\Master;

use App\Traits\AuditTrailTrait;
use App\Traits\FilterableSearchableTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcurementItem extends Model
{
    use SoftDeletes, FilterableSearchableTrait, AuditTrailTrait;

    protected $fillable = ['code', 'name', 'item_type', 'procurement_category_id', 'unit_of_measure_id', 'default_unit_price', 'description', 'is_active', 'created_by', 'updated_by', 'deleted_by'];
    protected $casts = ['default_unit_price' => 'decimal:2', 'is_active' => 'boolean'];

    public function category() { return $this->belongsTo(ProcurementCategory::class, 'procurement_category_id'); }
    public function unitOfMeasure() { return $this->belongsTo(UnitOfMeasure::class); }
}
