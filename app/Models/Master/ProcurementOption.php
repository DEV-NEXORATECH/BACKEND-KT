<?php

namespace App\Models\Master;

use App\Traits\AuditTrailTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcurementOption extends Model
{
    use SoftDeletes, AuditTrailTrait;

    protected $table = 'procurement_options';

    protected $fillable = ['code', 'name', 'option_type', 'is_active', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = ['is_active' => 'boolean'];
}
