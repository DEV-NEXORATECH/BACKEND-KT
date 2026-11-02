<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SavedReport extends Model
{
    use SoftDeletes;
    protected $fillable = ['user_id', 'name', 'configuration'];
    protected $casts = ['configuration' => 'array'];
}
