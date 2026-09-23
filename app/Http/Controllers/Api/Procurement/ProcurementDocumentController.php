<?php

namespace App\Http\Controllers\Api\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Procurement\GoodsReceipt;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseRequest;
use App\Models\Procurement\SupplierContractNotification;
use App\Models\Procurement\SupplierInvoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
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
        ])->all(), $purchaseRequest->lines->sum('total_amount'));
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
        ])->all(), $purchaseOrder->lines->sum('total_amount'));
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
        ])->all(), null, 'Received Quantity');
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
        ])->all(), $supplierInvoice->total_amount);
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
        ], [], null, null, true);
    }

    private function download(string $title, string $documentNumber, string $status, array $details, array $lines, ?float $total = null, ?string $quantityLabel = null, bool $letter = false): Response
    {
        $pdf = Pdf::loadView('exports.procurement.document', [
            'title' => $title,
            'documentNumber' => $documentNumber,
            'status' => $status,
            'details' => array_filter($details, fn ($value) => filled($value)),
            'lines' => $lines,
            'total' => $total,
            'quantityLabel' => $quantityLabel ?? 'Quantity',
            'letter' => $letter,
            'generatedAt' => now()->format('d M Y H:i'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download(Str::slug($documentNumber).'.pdf');
    }
}
