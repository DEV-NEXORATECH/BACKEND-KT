<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrantReportingDeadline extends Model
{
    use HasFactory;

    protected $table = 'grant_reporting_deadlines';

    protected $fillable = [
        'grant_agreement_id',
        'report_type',
        'period_label',
        'due_date',
        'submitted_date',
        'submitted_by',
        'document_path',
        'document_name',
        'status',
        'notes',
        'reminder_days',
    ];

    protected $casts = [
        'due_date' => 'date:Y-m-d',
        'submitted_date' => 'date:Y-m-d',
        'reminder_days' => 'integer',
    ];

    public function grantAgreement(): BelongsTo
    {
        return $this->belongsTo(GrantAgreement::class, 'grant_agreement_id');
    }
}
