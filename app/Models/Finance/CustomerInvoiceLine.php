<?php

namespace App\Models\Finance;

use App\Models\Master\BudgetLine;
use App\Models\Master\ChartOfAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerInvoiceLine extends Model
{
    protected $fillable = ['customer_invoice_id', 'revenue_account_id', 'budget_line_id', 'description', 'quantity', 'unit_price', 'total_amount', 'line_order'];

    protected $casts = ['quantity' => 'decimal:4', 'unit_price' => 'decimal:2', 'total_amount' => 'decimal:2'];

    public function customerInvoice(): BelongsTo { return $this->belongsTo(CustomerInvoice::class); }
    public function revenueAccount(): BelongsTo { return $this->belongsTo(ChartOfAccount::class, 'revenue_account_id'); }
    public function budgetLine(): BelongsTo { return $this->belongsTo(BudgetLine::class); }
}
