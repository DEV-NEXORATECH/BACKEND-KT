<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReportDefinition extends Model
{
    use SoftDeletes;
    protected $fillable = ['slug', 'label', 'source', 'dimensions', 'metrics', 'enabled', 'sort_order'];

    protected $casts = [
        'dimensions' => 'array',
        'metrics' => 'array',
        'enabled' => 'boolean',
    ];
}
