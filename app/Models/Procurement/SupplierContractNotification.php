<?php
namespace App\Models\Procurement;
use App\Models\Master\Vendor;
use App\Traits\FiscalYearScopedTrait;
use Illuminate\Database\Eloquent\Model;
class SupplierContractNotification extends Model
{
    protected $table = 'supplier_contract_notifications';
    use FiscalYearScopedTrait;
    protected $fillable = ['fiscal_year_id','scn_number','purchase_request_id','purchase_order_id','vendor_id','notification_date','completion_date','supplier_reference_number','receiving_location','donor_name','subject','notes','remarks','acceptable_for_payment','receipt_by_name','receipt_by_position','requester_name','requester_position','service_lines','status','attachments','created_by','issued_at'];
    protected $casts = ['notification_date' => 'date', 'completion_date' => 'date', 'issued_at' => 'datetime', 'attachments' => 'array', 'service_lines' => 'array', 'acceptable_for_payment' => 'boolean'];
    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function purchaseRequest() { return $this->belongsTo(PurchaseRequest::class); }
    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class); }
}
