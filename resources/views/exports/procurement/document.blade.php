<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; margin: 16mm 18mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #000; font-size: 9pt; line-height: 1.35; }
        .header { width: 100%; padding-bottom: 12pt; margin-bottom: 18pt; }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: top; }
        .company { width: 48%; color: #000; font-size: 9pt; font-weight: bold; }
        .company img { display: block; max-width: 150px; max-height: 62px; }
        .company-name { display: block; font-size: 8pt; margin-top: 5pt; }
        .company-meta { display: block; color: #555; font-size: 7.5pt; font-weight: normal; max-width: 250px; margin-top: 1pt; }
        .document { width: 52%; text-align: right; }
        .document-title { color: #000; font-size: 16pt; font-weight: bold; line-height: 1.15; }
        .document-meta { color: #555; font-size: 8pt; margin-top: 4pt; }
        .section-title { color: #000; font-size: 10pt; font-weight: bold; margin: 18pt 0 7pt; padding-bottom: 0; }
        .details-table, .items-table, .summary-table { width: 100%; border-collapse: collapse; }
        .details-table td { width: 50%; padding: 4pt 14pt 4pt 0; border: 0; vertical-align: top; }
        .label { color: #000; font-size: 8pt; font-weight: bold; display: inline-block; width: 52pt; vertical-align: top; }
        .label:after { content: ' :'; color: #555; }
        .value { color: #000; font-size: 9pt; display: inline; }
        .items-table { page-break-inside: auto; }
        .items-table thead { display: table-header-group; }
        .items-table th { color: #000; background: #FFFFFF; border-top: 0; border-bottom: 1pt solid #000; padding: 5pt 4pt; text-align: left; font-size: 8pt; }
        .items-table td { border: 0; padding: 4pt; vertical-align: top; font-size: 8.5pt; }
        .items-table tr { page-break-inside: avoid; }
        .number { text-align: right; white-space: nowrap; }
        .total-row td { border-top: 1pt solid #000; border-bottom: 0; font-weight: bold; font-size: 9.5pt; padding-top: 6pt; }
        .summary-wrap { width: 48%; margin-left: auto; margin-top: 8pt; }
        .summary-table td { padding: 3pt 0; }
        .summary-table td:last-child { text-align: right; }
        .grand-total td { border-top: 1pt solid #000; color: #000; font-weight: bold; padding-top: 5pt; }
        .notes { margin-top: 8pt; color: #111111; white-space: pre-line; }
        .footer { position: fixed; bottom: -10mm; left: 0; right: 0; border-top: .45pt solid #bbb; padding-top: 4pt; color: #555; font-size: 7.5pt; }
        .footer-table { width: 100%; border-collapse: collapse; }
        .footer-table td { width: 33.33%; }
        .footer-table td:nth-child(2) { text-align: center; }
        .footer-table td:last-child { text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table"><tr>
            <td class="company">
                @if(!empty($branding['logo']))<img src="{{ $branding['logo'] }}" alt="{{ $branding['name'] }}">@endif
                <div class="company-name">{{ $branding['name'] }}</div>
                @if(!empty($branding['address']))<div class="company-meta">{{ $branding['address'] }}</div>@endif
                @if(!empty($branding['email']))<div class="company-meta">{{ $branding['email'] }}</div>@endif
                @if(!empty($branding['phone']))<div class="company-meta">{{ $branding['phone'] }}</div>@endif
            </td>
            <td class="document"><div class="document-title">{{ strtoupper($title) }}</div><div class="document-meta">Document No. : {{ $documentNumber }}</div><div class="document-meta">Document Date : {{ \Carbon\Carbon::parse($generatedAt)->format('d M Y') }}</div></td>
        </tr></table>
    </div>

    @if(count($details))
        <div class="section-title">DOCUMENT INFORMATION</div>
        <table class="details-table">
            @foreach(array_chunk($details, 2, true) as $detailRow)
                <tr>@foreach($detailRow as $label => $value)<td><span class="label">{{ $label }}</span><span class="value">{{ $value }}</span></td>@endforeach @if(count($detailRow) === 1)<td></td>@endif</tr>
            @endforeach
        </table>
    @endif

    @if(count($lines))
        <div class="section-title">DETAIL ITEMS</div>
        <table class="items-table">
            <thead><tr><th style="width:24pt">No.</th><th>Description</th><th>Budget Line</th><th class="number">{{ $quantityLabel }}</th><th class="number">Unit Price</th><th class="number">Amount</th></tr></thead>
            <tbody>
                @foreach($lines as $index => $line)
                    <tr><td>{{ $index + 1 }}</td><td>{{ $line['description'] ?: '-' }}</td><td>{{ $line['budget_code'] ?: '-' }}</td><td class="number">{{ number_format((float) $line['quantity'], 2) }}</td><td class="number">{{ number_format((float) $line['unit_price'], 2) }}</td><td class="number">{{ number_format((float) $line['amount'], 2) }}</td></tr>
                @endforeach
                @if(!is_null($total))<tr class="total-row"><td colspan="5" class="number">TOTAL</td><td class="number">{{ number_format((float) $total, 2) }}</td></tr>@endif
            </tbody>
        </table>
    @elseif(!is_null($total))
        <div class="summary-wrap"><table class="summary-table"><tr class="grand-total"><td>Total Amount</td><td>{{ number_format((float) $total, 2) }}</td></tr></table></div>
    @endif

    @if(!empty($details['Notes']) || !empty($details['Justification']) || !empty($details['Description']))
        <div class="section-title">NOTES / JUSTIFICATION</div><div class="notes">{{ $details['Notes'] ?? ($details['Justification'] ?? ($details['Description'] ?? '')) }}</div>
    @endif

    <div class="footer"><table class="footer-table"><tr><td>Kaoem Telapak ERP</td><td>Generated {{ $generatedAt }}</td><td></td></tr></table></div>
</body>
</html>
