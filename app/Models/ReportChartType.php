<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReportChartType extends Model
{
    use SoftDeletes;
    protected $fillable = ['value', 'label', 'enabled', 'sort_order'];
    protected $casts = ['enabled' => 'boolean'];
}
