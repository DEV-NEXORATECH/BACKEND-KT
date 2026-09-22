<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavedReport extends Model
{
    protected $fillable = ['user_id', 'name', 'configuration'];
    protected $casts = ['configuration' => 'array'];
}
