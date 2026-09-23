<?php
namespace App\Models\Procurement;
use App\Models\Master\Vendor;
use Illuminate\Database\Eloquent\Model;
class SupplierContractNotification extends Model
{
    protected $table = 'supplier_contract_notifications';
    protected $fillable = ['scn_number','purchase_request_id','purchase_order_id','vendor_id','notification_date','subject','notes','status','attachments','created_by','issued_at'];
    protected $casts = ['notification_date' => 'date', 'issued_at' => 'datetime', 'attachments' => 'array'];
    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function purchaseRequest() { return $this->belongsTo(PurchaseRequest::class); }
    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class); }
}
