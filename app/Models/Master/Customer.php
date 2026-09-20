<?php

namespace App\Models\Master;

use App\Traits\AuditTrailTrait;
use App\Traits\FilterableSearchableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes, FilterableSearchableTrait, AuditTrailTrait;

    protected $fillable = ['code', 'name', 'email', 'phone', 'address', 'is_active', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = ['is_active' => 'boolean'];
}
