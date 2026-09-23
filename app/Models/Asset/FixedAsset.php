<?php

namespace App\Models\Asset;

use App\Models\Accounting\Journal;
use App\Models\Master\AssetCategory;
use App\Models\Master\Donor;
use App\Models\Master\Employee;
use App\Models\Master\Program;
use App\Models\Master\Project;
use App\Models\Master\Vendor;
use App\Models\Procurement\GoodsReceipt;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\SupplierInvoice;
use App\Traits\AuditTrailTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FixedAsset extends Model
{
    use SoftDeletes, AuditTrailTrait;

    protected $fillable = ['asset_code', 'asset_name', 'asset_category_id', 'acquisition_date', 'acquisition_cost', 'vendor_id', 'purchase_order_id', 'goods_receipt_id', 'supplier_invoice_id', 'donor_id', 'program_id', 'project_id', 'location', 'custodian_id', 'useful_life_months', 'depreciation_method', 'accumulated_depreciation', 'net_book_value', 'status', 'notes', 'attachments', 'journal_id', 'capitalized_at', 'capitalized_by', 'disposed_date', 'disposal_reason', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = ['acquisition_date' => 'date', 'acquisition_cost' => 'decimal:2', 'accumulated_depreciation' => 'decimal:2', 'net_book_value' => 'decimal:2', 'attachments' => 'array', 'capitalized_at' => 'datetime', 'disposed_date' => 'date'];

    public function category(): BelongsTo { return $this->belongsTo(AssetCategory::class, 'asset_category_id'); }
    public function vendor(): BelongsTo { return $this->belongsTo(Vendor::class); }
    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class); }
    public function goodsReceipt(): BelongsTo { return $this->belongsTo(GoodsReceipt::class); }
    public function supplierInvoice(): BelongsTo { return $this->belongsTo(SupplierInvoice::class); }
    public function donor(): BelongsTo { return $this->belongsTo(Donor::class); }
    public function program(): BelongsTo { return $this->belongsTo(Program::class); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function custodian(): BelongsTo { return $this->belongsTo(Employee::class, 'custodian_id'); }
    public function journal(): BelongsTo { return $this->belongsTo(Journal::class); }
    public function depreciations(): HasMany { return $this->hasMany(AssetDepreciation::class); }
}
