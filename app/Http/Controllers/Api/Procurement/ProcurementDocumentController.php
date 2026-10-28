<?php

namespace App\Http\Controllers\Api\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Procurement\Contract;
use App\Models\Procurement\GoodsReceipt;
use App\Models\Procurement\ProcurementWaiver;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseRequest;
use App\Models\Procurement\SupplierContractNotification;
use App\Models\Procurement\SupplierInvoice;
use App\Models\Master\Organization;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcurementDocumentController extends Controller
{
    public function purchaseRequest(PurchaseRequest $purchaseRequest): Response
    {
        $purchaseRequest->load(['requester', 'department', 'project.program', 'vendor', 'lines.budgetLine']);

        return $this->download('Purchase Request', $purchaseRequest->pr_number, $purchaseRequest->status, [
            'Request Date' => $purchaseRequest->request_date?->format('d M Y'),
            'Requester' => $purchaseRequest->requester?->name,
            'Department' => $purchaseRequest->department?->name,
            'Project' => $purchaseRequest->project?->code.' - '.$purchaseRequest->project?->name,
            'Program' => $purchaseRequest->project?->program?->code.' - '.$purchaseRequest->project?->program?->name,
            'Vendor' => $purchaseRequest->vendor?->name,
            'Justification' => $purchaseRequest->justification,
        ], $purchaseRequest->lines->map(fn ($line) => [
            'description' => $line->item_description,
            'budget_code' => $line->budgetLine?->line_code,
            'quantity' => $line->quantity,
            'unit_price' => $line->unit_price,
            'amount' => $line->total_amount,
        ])->all(), $purchaseRequest->lines->sum('total_amount'), null, false, 'exports.procurement.purchase-request');
    }

    public function purchaseOrder(PurchaseOrder $purchaseOrder): Response
    {
        $purchaseOrder->load(['purchaseRequest.project.program', 'vendor', 'rfq', 'cba', 'lines.budgetLine']);

        return $this->download('Purchase Order / Contract', $purchaseOrder->po_number, $purchaseOrder->status, [
            'PO Date' => $purchaseOrder->po_date?->format('d M Y'),
            'Contract Number' => $purchaseOrder->contract_number,
            'Contract Date' => $purchaseOrder->contract_date?->format('d M Y'),
            'Vendor' => $purchaseOrder->vendor?->name,
            'Source PR' => $purchaseOrder->purchaseRequest?->pr_number,
            'Source RFQ' => $purchaseOrder->rfq?->rfq_number,
            'Source CBA' => $purchaseOrder->cba?->cba_number,
            'Project' => $purchaseOrder->purchaseRequest?->project?->code.' - '.$purchaseOrder->purchaseRequest?->project?->name,
            'Terms' => $purchaseOrder->terms,
        ], $purchaseOrder->lines->map(fn ($line) => [
            'description' => $line->item_description,
            'budget_code' => $line->budgetLine?->line_code,
            'quantity' => $line->quantity,
            'unit_price' => $line->unit_price,
            'amount' => $line->total_amount,
        ])->all(), $purchaseOrder->lines->sum('total_amount'), null, false, 'exports.procurement.purchase-order');
    }

    public function goodsReceipt(GoodsReceipt $goodsReceipt): Response
    {
        $goodsReceipt->load(['purchaseOrder.purchaseRequest.project', 'purchaseOrder.vendor', 'lines.purchaseOrderLine.budgetLine']);

        return $this->download('Goods Receipt Note', $goodsReceipt->grn_number, $goodsReceipt->status, [
            'Receipt Date' => $goodsReceipt->receipt_date?->format('d M Y'),
            'Purchase Order' => $goodsReceipt->purchaseOrder?->po_number,
            'Purchase Request' => $goodsReceipt->purchaseOrder?->purchaseRequest?->pr_number,
            'Vendor' => $goodsReceipt->purchaseOrder?->vendor?->name,
            'Project' => $goodsReceipt->purchaseOrder?->purchaseRequest?->project?->code.' - '.$goodsReceipt->purchaseOrder?->purchaseRequest?->project?->name,
            'Notes' => $goodsReceipt->notes,
        ], $goodsReceipt->lines->map(fn ($line) => [
            'description' => $line->purchaseOrderLine?->item_description,
            'budget_code' => $line->purchaseOrderLine?->budgetLine?->line_code,
            'quantity' => $line->received_quantity,
            'unit_price' => $line->purchaseOrderLine?->unit_price,
            'amount' => (float) $line->received_quantity * (float) ($line->purchaseOrderLine?->unit_price ?? 0),
        ])->all(), null, 'Received Quantity', false, 'exports.procurement.goods-receipt');
    }

    public function supplierInvoice(SupplierInvoice $supplierInvoice): Response
    {
        $supplierInvoice->load(['purchaseOrder.purchaseRequest.project.program', 'goodsReceipt', 'vendor', 'lines.purchaseOrderLine.budgetLine']);

        return $this->download('Supplier Invoice Register', $supplierInvoice->invoice_number, $supplierInvoice->status, [
            'Invoice Date' => $supplierInvoice->invoice_date?->format('d M Y'),
            'Due Date' => $supplierInvoice->due_date?->format('d M Y'),
            'Vendor' => $supplierInvoice->vendor?->name,
            'Purchase Order' => $supplierInvoice->purchaseOrder?->po_number,
            'Goods Receipt' => $supplierInvoice->goodsReceipt?->grn_number,
            '3-Way Match' => Str::headline((string) $supplierInvoice->match_status),
            'Project' => $supplierInvoice->purchaseOrder?->purchaseRequest?->project?->code.' - '.$supplierInvoice->purchaseOrder?->purchaseRequest?->project?->name,
            'Notes' => $supplierInvoice->notes,
        ], $supplierInvoice->lines->map(fn ($line) => [
            'description' => $line->item_description,
            'budget_code' => $line->purchaseOrderLine?->budgetLine?->line_code,
            'quantity' => $line->quantity,
            'unit_price' => $line->unit_price,
            'amount' => $line->total_amount,
        ])->all(), $supplierInvoice->total_amount, null, false, 'exports.procurement.supplier-invoice');
    }

    public function scn(SupplierContractNotification $supplierContractNotification): Response
    {
        $supplierContractNotification->load(['vendor', 'purchaseRequest.project', 'purchaseOrder.purchaseRequest.project']);

        return $this->download('Supplier Contract Notification', $supplierContractNotification->scn_number, $supplierContractNotification->status, [
            'Notification Date' => $supplierContractNotification->notification_date?->format('d M Y'),
            'Subject' => $supplierContractNotification->subject,
            'Vendor' => $supplierContractNotification->vendor?->name,
            'Purchase Request' => $supplierContractNotification->purchaseRequest?->pr_number ?: $supplierContractNotification->purchaseOrder?->purchaseRequest?->pr_number,
            'Purchase Order' => $supplierContractNotification->purchaseOrder?->po_number,
            'Project' => $supplierContractNotification->purchaseRequest?->project?->name ?: $supplierContractNotification->purchaseOrder?->purchaseRequest?->project?->name,
            'Notes' => $supplierContractNotification->notes,
        ], [], null, null, true, 'exports.procurement.supplier-contract-notification');
    }

    public function contract(Contract $contract): Response
    {
        $contract->load(['vendor', 'project', 'donor']);

        return $this->download('Procurement Contract', $contract->contract_number, $contract->status, [
            'Contract Type' => Str::headline((string) $contract->contract_type),
            'Contract Title' => $contract->title,
            'Vendor / Party' => $contract->vendor?->name ?: $contract->vendor_name,
            'Vendor Contact' => $contract->vendor_contact,
            'Project' => $contract->project?->name ?: $contract->project_name,
            'Donor' => $contract->donor?->name ?: $contract->donor_name,
            'Start Date' => $contract->start_date?->format('d M Y'),
            'End Date' => $contract->end_date?->format('d M Y'),
            'Payment Terms' => $contract->payment_terms,
            'Scope of Work' => $contract->scope_of_work,
            'Notes' => $contract->notes,
        ], [], (float) $contract->total_value, null, true, 'exports.procurement.contract');
    }

    public function waiver(ProcurementWaiver $waiver): Response
    {
        $waiver->load(['purchaseRequest', 'project', 'vendor', 'approver']);

        return $this->download('Procurement Waiver & Justification', $waiver->waiver_number, $waiver->status, [
            'Waiver Date' => $waiver->date?->format('d M Y'),
            'Description' => $waiver->description,
            'Justification' => $waiver->justification,
            'Purchase Request' => $waiver->purchaseRequest?->pr_number,
            'Project' => $waiver->project?->name ?: $waiver->project_name,
            'Vendor' => $waiver->vendor?->name ?: $waiver->vendor_name,
            'Approved By' => $waiver->approver?->name,
            'Approved At' => $waiver->approved_at?->format('d M Y H:i'),
            'Notes' => $waiver->notes,
        ], [], (float) $waiver->total_amount, null, true, 'exports.procurement.waiver');
    }

    private function download(string $title, string $documentNumber, string $status, array $details, array $lines, ?float $total = null, ?string $quantityLabel = null, bool $letter = false, string $view = 'exports.procurement.document'): Response
    {
        $organization = Organization::query()->where('is_active', true)->orderBy('id')->first();
        $office = $organization?->officeLocations()->where('is_active', true)->orderByDesc('is_head_office')->orderBy('id')->first();
        $logo = null;
        if ($organization?->logo_url) {
            $logoPath = ltrim(parse_url($organization->logo_url, PHP_URL_PATH) ?: $organization->logo_url, '/');
            if (Storage::disk('public')->exists($logoPath)) {
                $mime = Storage::disk('public')->mimeType($logoPath) ?: 'image/png';
                $logo = 'data:'.$mime.';base64,'.base64_encode(Storage::disk('public')->get($logoPath));
            }
        }
        if (!$logo && is_file(public_path('assets/kaoem-telapak-logo-transparent.png'))) {
            $logo = 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('assets/kaoem-telapak-logo-transparent.png')));
        }
        $pdf = Pdf::loadView($view, [
            'title' => $title,
            'documentNumber' => $documentNumber,
            'status' => $status,
            'details' => array_filter($details, fn ($value) => filled($value)),
            'lines' => $lines,
            'total' => $total,
            'quantityLabel' => $quantityLabel ?? 'Quantity',
            'letter' => $letter,
            'generatedAt' => now()->format('d M Y H:i'),
            'branding' => [
                'name' => $organization?->legal_name ?: $organization?->name ?: 'Kaoem Telapak',
                'address' => $office?->address ?: $organization?->address,
                'email' => $office?->email ?: $organization?->email,
                'phone' => $office?->phone,
                'logo' => $logo,
            ],
        ])->setPaper('a4', 'portrait');

        // Keep page numbering in the shared footer. The Blade template owns the
        // layout; the canvas only adds the dynamic page counter once.
        $pdf->getDomPDF()->render();
        $pdf->getDomPDF()->getCanvas()->page_text(510, 820, 'Page {PAGE_NUM} of {PAGE_COUNT}', null, 8, [71 / 255, 85 / 255, 105 / 255]);

        return $pdf->download(Str::slug($documentNumber).'.pdf');
    }
}
