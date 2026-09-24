<?php

namespace App\Models\Finance;

use App\Models\Accounting\Journal;
use App\Models\Master\Customer;
use App\Models\Master\Donor;
use App\Models\Master\Program;
use App\Models\Master\Project;
use App\Traits\AuditTrailTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerInvoice extends Model
{
    use SoftDeletes, AuditTrailTrait;

    protected $fillable = ['invoice_number', 'customer_id', 'project_id', 'donor_id', 'program_id', 'tax_id', 'invoice_date', 'due_date', 'currency_code', 'exchange_rate', 'status', 'total_amount', 'received_amount', 'description', 'journal_id', 'posted_at', 'posted_by', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = ['invoice_date' => 'date', 'due_date' => 'date', 'exchange_rate' => 'decimal:6', 'total_amount' => 'decimal:2', 'received_amount' => 'decimal:2', 'posted_at' => 'datetime'];

    public function lines(): HasMany { return $this->hasMany(CustomerInvoiceLine::class)->orderBy('line_order'); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function donor(): BelongsTo { return $this->belongsTo(Donor::class); }
    public function program(): BelongsTo { return $this->belongsTo(Program::class); }
    public function journal(): BelongsTo { return $this->belongsTo(Journal::class); }
    public function tax(): BelongsTo { return $this->belongsTo(\App\Models\Master\Tax::class); }
}
