<?php
namespace App\Models\Master;
use App\Traits\AuditTrailTrait; use App\Traits\FilterableSearchableTrait; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\HasMany; use Illuminate\Database\Eloquent\SoftDeletes;
class AccountCategory extends Model { use SoftDeletes, FilterableSearchableTrait, AuditTrailTrait; protected $fillable=['code','name','account_type','description','is_active','created_by','updated_by','deleted_by']; protected $casts=['is_active'=>'boolean']; public function accounts(): HasMany { return $this->hasMany(ChartOfAccount::class); } }
