<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class TaxCalendarReminder extends Model
{
    protected $fillable = ['user_id', 'event_id', 'event_title', 'reminded_at'];

    protected $casts = ['reminded_at' => 'datetime'];
}
