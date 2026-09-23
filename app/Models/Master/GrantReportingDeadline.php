<?php
namespace App\Models\Master; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class GrantReportingDeadline extends Model {protected $fillable=['grant_agreement_id','report_type','due_date','status','notes'];protected $casts=['due_date'=>'date'];public function grantAgreement():BelongsTo{return $this->belongsTo(GrantAgreement::class);}}
