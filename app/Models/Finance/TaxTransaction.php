<?php

namespace App\Models\Finance;

use App\Models\Master\Tax;
use App\Traits\AuditTrailTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxTransaction extends Model
{
    use SoftDeletes, AuditTrailTrait;

    protected $fillable = ['tax_id', 'transaction_type', 'source_type', 'source_id', 'reference', 'transaction_date', 'direction', 'taxable_amount', 'tax_rate', 'tax_amount', 'net_amount', 'gross_amount', 'e_faktur_reference', 'e_bupot_reference', 'status', 'notes', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = [
        'transaction_date' => 'date',
        'taxable_amount' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'tax_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'gross_amount' => 'decimal:2',
    ];

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }
}
