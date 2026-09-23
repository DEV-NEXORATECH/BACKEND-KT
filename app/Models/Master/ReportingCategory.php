<?php

namespace App\Models\Master;

use App\Traits\AuditTrailTrait;
use App\Traits\FilterableSearchableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReportingCategory extends Model
{
    use HasFactory, SoftDeletes, FilterableSearchableTrait, AuditTrailTrait;

    protected $table = 'reporting_categories';
    protected $fillable = ['code', 'name', 'category_type', 'description', 'is_active', 'created_by', 'updated_by', 'deleted_by'];
    protected $casts = ['is_active' => 'boolean'];
}
