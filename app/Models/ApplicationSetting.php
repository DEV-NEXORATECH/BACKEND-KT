<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApplicationSetting extends Model
{
    use SoftDeletes;
    protected $fillable = ['key', 'value', 'updated_by'];

    protected $casts = ['value' => 'array'];
}
