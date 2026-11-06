<?php

namespace App\Models\Master;

use App\Traits\AuditTrailTrait;
use App\Traits\FilterableSearchableTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenseClass extends Model
{
    use SoftDeletes, FilterableSearchableTrait, AuditTrailTrait;

    protected $fillable = ['donor_id', 'code', 'name', 'detail_type', 'description', 'is_active', 'created_by', 'updated_by', 'deleted_by'];
    protected $casts = ['is_active' => 'boolean'];

    public function donor() { return $this->belongsTo(Donor::class); }
}
