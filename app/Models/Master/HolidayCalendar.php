<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HolidayCalendar extends Model
{
    use SoftDeletes;
    protected $fillable = ['date', 'name', 'type', 'is_active', 'created_by', 'updated_by'];

    protected $casts = ['date' => 'date', 'is_active' => 'boolean'];
}
