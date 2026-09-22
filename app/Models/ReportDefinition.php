<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportDefinition extends Model
{
    protected $fillable = ['slug', 'label', 'source', 'dimensions', 'metrics', 'enabled', 'sort_order'];

    protected $casts = [
        'dimensions' => 'array',
        'metrics' => 'array',
        'enabled' => 'boolean',
    ];
}
