<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportChartType extends Model
{
    protected $fillable = ['value', 'label', 'enabled', 'sort_order'];
    protected $casts = ['enabled' => 'boolean'];
}
