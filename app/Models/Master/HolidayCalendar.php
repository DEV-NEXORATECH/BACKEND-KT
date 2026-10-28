<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class HolidayCalendar extends Model
{
    protected $fillable = ['date', 'name', 'type', 'is_active', 'created_by', 'updated_by'];

    protected $casts = ['date' => 'date', 'is_active' => 'boolean'];
}
