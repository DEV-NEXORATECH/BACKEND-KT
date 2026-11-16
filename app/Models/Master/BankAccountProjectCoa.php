<?php

namespace App\Models\Master;

use App\Traits\AuditTrailTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankAccountProjectCoa extends Model
{
    use HasFactory, SoftDeletes, AuditTrailTrait;

    protected $table = 'bank_account_project_coas';

    protected $fillable = [
        'bank_account_id',
        'project_id',
        'gl_account_id',
        'fiscal_year_id',
        'relation_no',
        'account_name',
        'start_date',
        'end_date',
        'opening_balance',
        'notes',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'opening_balance' => 'decimal:2',
    ];

    protected $appends = ['relation_number'];

    public function getRelationNumberAttribute(): string
    {
        return $this->relation_no
            ?: ($this->project?->relation_no
                ?: ($this->project?->grantAgreement?->relation_no
                    ?: ''));
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function glAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'gl_account_id');
    }

    public function fiscalYear()
    {
        return $this->belongsTo(FiscalYear::class, 'fiscal_year_id');
    }
}
