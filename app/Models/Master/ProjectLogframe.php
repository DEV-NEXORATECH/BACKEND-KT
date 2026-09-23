<?php
namespace App\Models\Master; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ProjectLogframe extends Model {protected $fillable=['project_id','level','code','description','indicator','baseline','target','actual','unit','period_start','period_end','is_active']; protected $casts=['baseline'=>'decimal:2','target'=>'decimal:2','actual'=>'decimal:2','period_start'=>'date','period_end'=>'date','is_active'=>'boolean']; public function project():BelongsTo{return $this->belongsTo(Project::class);}}
