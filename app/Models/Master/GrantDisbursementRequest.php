<?php

namespace App\Models\Master;

use App\Traits\AuditTrailTrait;
use App\Traits\FilterableSearchableTrait;
use App\Traits\FiscalYearScopedTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GrantDisbursementRequest extends Model
{
    use HasFactory, SoftDeletes, FilterableSearchableTrait, AuditTrailTrait, FiscalYearScopedTrait;

    protected $table = 'grant_disbursement_requests';

    protected $fillable = [
        'fiscal_year_id',
        'grant_agreement_id',
        'bank_account_id',
        'request_no',
        'period_type',
        'period_label',
        'request_date',
        'currency_id',
        'forecast_total_amount',
        'requested_amount',
        'contract_fx_rate',
        'requested_amount_idr',
        'status',
        'notes',
        'attachment_path',
        'received_date',
        'received_amount',
        'actual_fx_rate',
        'received_amount_idr',
        'fx_gain_loss',
        'received_bank_account_id',
        'receipt_proof_path',
        'received_notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'request_date' => 'date',
        'received_date' => 'date',
        'forecast_total_amount' => 'decimal:2',
        'requested_amount' => 'decimal:2',
        'contract_fx_rate' => 'decimal:4',
        'requested_amount_idr' => 'decimal:2',
        'received_amount' => 'decimal:2',
        'actual_fx_rate' => 'decimal:4',
        'received_amount_idr' => 'decimal:2',
        'fx_gain_loss' => 'decimal:2',
    ];

    public function grantAgreement()
    {
        return $this->belongsTo(GrantAgreement::class, 'grant_agreement_id');
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function receivedBankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'received_bank_account_id');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function forecastItems()
    {
        return $this->hasMany(GrantForecastItem::class, 'disbursement_request_id');
    }
}

