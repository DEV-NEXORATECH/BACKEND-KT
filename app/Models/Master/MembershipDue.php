<?php

namespace App\Models\Master;

use App\Models\Accounting\Journal;
use App\Models\Finance\Payment;
use App\Traits\AuditTrailTrait;
use App\Traits\FilterableSearchableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MembershipDue extends Model
{
    use HasFactory, SoftDeletes, FilterableSearchableTrait, AuditTrailTrait;

    protected $fillable = [
        'customer_id', 'fiscal_year_id', 'reference', 'due_date', 'amount', 'status',
        'paid_at', 'payment_reference', 'payment_id', 'journal_id', 'notes',
        'created_by', 'updated_by',
    ];

    protected $casts = ['due_date' => 'date', 'paid_at' => 'date', 'amount' => 'decimal:2'];

    public function member() { return $this->belongsTo(Customer::class, 'customer_id'); }
    public function fiscalYear() { return $this->belongsTo(FiscalYear::class); }
    public function payment() { return $this->belongsTo(Payment::class); }
    public function journal() { return $this->belongsTo(Journal::class); }
}
