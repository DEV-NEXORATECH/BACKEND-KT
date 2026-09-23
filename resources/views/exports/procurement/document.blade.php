<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28px 32px 55px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #1f2937; font-size: 9px; line-height: 1.45; }
        .brand { color: #075e54; font-size: 14px; font-weight: bold; letter-spacing: .5px; }
        .document-title { font-size: 17px; color: #0f172a; font-weight: bold; margin-top: 4px; }
        .header { border-bottom: 2px solid #0f766e; padding-bottom: 13px; margin-bottom: 17px; }
        .header-table, .details-table, .items-table, .signature-table { width: 100%; border-collapse: collapse; }
        .document-number { font-size: 12px; font-weight: bold; text-align: right; }
        .status { color: #075e54; background: #e7f5f1; font-size: 8px; font-weight: bold; padding: 4px 7px; text-transform: uppercase; }
        .details-table td { width: 50%; padding: 5px 11px 5px 0; vertical-align: top; border-bottom: 1px solid #e5e7eb; }
        .details-table .label { color: #64748b; display: block; font-size: 8px; text-transform: uppercase; margin-bottom: 2px; }
        .section-title { color: #0f766e; font-size: 10px; font-weight: bold; text-transform: uppercase; margin: 20px 0 7px; }
        .items-table th { background: #0f766e; color: #fff; text-align: left; padding: 7px 6px; font-size: 8px; }
        .items-table td { padding: 7px 6px; border-bottom: 1px solid #dbe5e3; vertical-align: top; }
        .items-table .number { text-align: right; white-space: nowrap; }
        .total-row td { border-top: 2px solid #0f766e; font-weight: bold; font-size: 10px; }
        .letter-body { font-size: 10px; line-height: 1.65; margin-top: 18px; min-height: 190px; }
        .signature-table { margin-top: 38px; }
        .signature-table td { width: 33.33%; vertical-align: top; text-align: center; padding: 0 10px; }
        .signature-space { height: 45px; }
        .sign-name { border-top: 1px solid #64748b; padding-top: 5px; font-weight: bold; }
        .sign-role { color: #64748b; font-size: 8px; }
        .footer { position: fixed; bottom: -35px; left: 0; right: 0; color: #64748b; font-size: 7px; border-top: 1px solid #dbe5e3; padding-top: 6px; }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table"><tr><td><div class="brand">KAOEM TELAPAK</div><div class="document-title">{{ $title }}</div></td><td><div class="document-number">{{ $documentNumber }}</div><div style="text-align:right; margin-top:8px;"><span class="status">{{ $status }}</span></div></td></tr></table>
    </div>

    <table class="details-table">
        @foreach(array_chunk($details, 2, true) as $detailRow)
            <tr>
                @foreach($detailRow as $label => $value)<td><span class="label">{{ $label }}</span>{{ $value }}</td>@endforeach
                @if(count($detailRow) === 1)<td></td>@endif
            </tr>
        @endforeach
    </table>

    @if($letter)
        <div class="letter-body">
            <p>Dear {{ $details['Vendor'] ?? 'Supplier' }},</p>
            <p>This notification confirms the procurement decision and related contract information stated above. Please acknowledge receipt and coordinate the required next steps with the Kaoem Telapak procurement team.</p>
            <p>{{ $details['Notes'] ?? '' }}</p>
            <p>Thank you for your cooperation.</p>
        </div>
    @elseif(count($lines))
        <div class="section-title">Items / Services</div>
        <table class="items-table">
            <thead><tr><th style="width:24px">No.</th><th>Description</th><th>Budget Line</th><th class="number">{{ $quantityLabel }}</th><th class="number">Unit Price</th><th class="number">Amount</th></tr></thead>
            <tbody>
                @foreach($lines as $index => $line)
                    <tr><td>{{ $index + 1 }}</td><td>{{ $line['description'] ?: '-' }}</td><td>{{ $line['budget_code'] ?: '-' }}</td><td class="number">{{ number_format((float) $line['quantity'], 2) }}</td><td class="number">{{ number_format((float) $line['unit_price'], 2) }}</td><td class="number">{{ number_format((float) $line['amount'], 2) }}</td></tr>
                @endforeach
                @if(!is_null($total))<tr class="total-row"><td colspan="5" class="number">TOTAL</td><td class="number">{{ number_format((float) $total, 2) }}</td></tr>@endif
            </tbody>
        </table>
    @endif

    <table class="signature-table"><tr>
        <td><div>Prepared by</div><div class="signature-space"></div><div class="sign-name">________________________</div><div class="sign-role">Procurement / Requester</div></td>
        <td><div>Reviewed by</div><div class="signature-space"></div><div class="sign-name">________________________</div><div class="sign-role">Finance / Budget Holder</div></td>
        <td><div>Approved by</div><div class="signature-space"></div><div class="sign-name">________________________</div><div class="sign-role">Authorized Signatory</div></td>
    </tr></table>

    <div class="footer">Kaoem Telapak ERP - {{ $title }} {{ $documentNumber }} - Generated {{ $generatedAt }}</div>
</body>
</html>
