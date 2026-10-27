# Procurement PDF Audit

Audit date: 27 October 2026

## Verified PDF source

- Routes: `routes/api.php` exposes PDF endpoints for purchase requests, purchase orders, goods receipts, supplier invoices, supplier contract notifications, and waivers.
- Controller: `app/Http/Controllers/Api/Procurement/ProcurementDocumentController.php`.
- Shared template: `resources/views/exports/procurement/document.blade.php`.
- Renderer: `Barryvdh\DomPDF\Facade\Pdf`, A4 portrait.
- Branding: the controller now reads the active `Organization` record and its configured `logo_url`, address, email, and legal/name fields. The PDF does not create a placeholder logo.

## Module mapping

| Module | Existing source used by PDF | Detail lines | Existing approval/signature data | Result |
|---|---|---:|---|---|
| Purchase Request | `PurchaseRequest`, requester, department, project/program, vendor, `lines.budgetLine` | Yes | Not supplied by the existing PDF controller | Formal document header, compact details, item table and total |
| Purchase Order | `PurchaseOrder`, purchase request, vendor, RFQ, CBA, `lines.budgetLine` | Yes | Not supplied by the existing PDF controller | Formal PO/contract document with source references and terms |
| Goods Receipt | `GoodsReceipt`, purchase order/vendor/project, `lines.purchaseOrderLine` | Yes | Not supplied by the existing PDF controller | Received-quantity table; no invented warehouse/inspection columns |
| Supplier Invoice | `SupplierInvoice`, vendor, PO, GRN, match status, `lines.purchaseOrderLine` | Yes | Not supplied by the existing PDF controller | Invoice register with existing amount and 3-way match fields |
| Supplier Contract Notification | `SupplierContractNotification`, vendor, PR/PO, project, notes | No | Not supplied by the existing PDF controller | Information-only notification; no invented approval trail |
| Procurement Contract | `Contract`, vendor/party, project, donor, dates, terms, scope, notes | No | Not supplied by the existing PDF controller | Contract information document; no invented clauses or signatures |
| Waiver & Justification | `ProcurementWaiver`, PR, project, vendor, approver, approval timestamp, notes | No | Existing approver fields are displayed as source details | Waiver information and justification document |
| Dashboard | No procurement-specific PDF endpoint found in the audited controller/routes | N/A | N/A | No new dashboard PDF was invented |

## Redesign applied

- Formal two-sided header with configured company branding and document metadata.
- Navy divider and restrained accounting palette (`#0B2345`, white, black, `#CBD5E1`, `#475569`).
- White tables with compact rows, thin horizontal rules, aligned numeric columns, wrapped descriptions, and repeated table headers.
- Removed dashboard-style cards, rounded table containers, zebra rows, fake status badges, and placeholder signature blocks.
- Totals are shown only when the existing controller provides a total.
- Notes/justification are shown only when present in the existing details array.
- Existing permission checks, routes, workflow, relationships, and calculations were not changed.

## Validation

- PHP syntax check passed for `ProcurementDocumentController.php`.
- The shared Blade template uses only fields passed by the existing controller and its loaded relationships.
- No migration, model, request field, approval stage, or dummy transaction value was added.
