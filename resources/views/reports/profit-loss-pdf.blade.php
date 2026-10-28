<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Laba Rugi (Profit & Loss)</title>
    <style>
        @if(file_exists(public_path('fonts/Montserrat-Variable.ttf')))
        @font-face { font-family: 'Montserrat'; font-style: normal; font-weight: 100 900; src: url('{{ public_path('fonts/Montserrat-Variable.ttf') }}') format('truetype'); }
        @endif
        * { margin: 0; padding: 0; box-sizing: border-box; }
        @page { size: A4 portrait; margin: 16mm 18mm; }
        body { font-family: 'Montserrat', Arial, Helvetica, sans-serif; font-size: 10pt; color: #000; }
        .header { text-align: left; padding-bottom: 12px; margin-bottom: 18px; }
        .header h1 { font-size: 16pt; color: #000; margin-bottom: 2px; }
        .header h2 { font-size: 12pt; color: #333; font-weight: normal; margin-bottom: 4px; }
        .header p { font-size: 9pt; color: #666; }
        .meta { text-align: right; font-size: 9pt; color: #666; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th { background: #fff; color: #000; text-align: left; padding: 6px 8px; font-size: 9pt; border-bottom: 1px solid #000; }
        td { border: 0; padding: 5px 8px; font-size: 9pt; }
        .right { text-align: right; }
        .section-title { background: #fff; font-weight: bold; padding: 6px 0; font-size: 10pt; color: #000; }
        .subtotal { font-weight: bold; background: #fff; }
        .grand-total { font-weight: bold; background: #fff; color: #000; border-top: 1px solid #000; }
        .surplus, .deficit { color: #000; }
        .footer { text-align: center; font-size: 8pt; color: #999; margin-top: 20px; border-top: 1px solid #ccc; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>PERKUMPULAN KAOEM TELAPAK</h1>
        <h2>LAPORAN LABA RUGI (PROFIT & LOSS)</h2>
        <p>Periode: {{ $period_label }}</p>
    </div>

    <div class="meta">
        Dicetak pada: {{ now()->format('d/m/Y H:i') }}
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:15%">Kode Akun</th>
                <th style="width:45%">Nama Akun</th>
                <th class="right" style="width:20%">Debit (IDR)</th>
                <th class="right" style="width:20%">Kredit (IDR)</th>
            </tr>
        </thead>
        <tbody>
            {{-- PENDAPATAN --}}
            <tr><td colspan="4" class="section-title">PENDAPATAN (REVENUE)</td></tr>
            @php $totalRevenue = 0; @endphp
            @foreach ($rows->where('account_type', 'revenue') as $row)
            <tr>
                <td>{{ $row['code'] }}</td>
                <td>{{ $row['name'] }}</td>
                <td class="right">{{ number_format($row['debit'], 2, ',', '.') }}</td>
                <td class="right">{{ number_format($row['credit'], 2, ',', '.') }}</td>
            </tr>
            @php $totalRevenue += $row['balance']; @endphp
            @endforeach
            <tr class="subtotal">
                <td colspan="3">Total Pendapatan</td>
                <td class="right">{{ number_format($totalRevenue, 2, ',', '.') }}</td>
            </tr>

            {{-- BEBAN --}}
            <tr><td colspan="4" class="section-title">BEBAN (EXPENSES)</td></tr>
            @php $totalExpense = 0; @endphp
            @foreach ($rows->where('account_type', 'expense') as $row)
            <tr>
                <td>{{ $row['code'] }}</td>
                <td>{{ $row['name'] }}</td>
                <td class="right">{{ number_format($row['debit'], 2, ',', '.') }}</td>
                <td class="right">{{ number_format($row['credit'], 2, ',', '.') }}</td>
            </tr>
            @php $totalExpense += $row['balance']; @endphp
            @endforeach
            <tr class="subtotal">
                <td colspan="3">Total Beban</td>
                <td class="right">{{ number_format($totalExpense, 2, ',', '.') }}</td>
            </tr>

            {{-- SURPLUS / DEFISIT --}}
            @php $netResult = $totalRevenue - $totalExpense; @endphp
            <tr class="grand-total">
                <td colspan="3">SURPLUS / (DEFISIT)</td>
                <td class="right {{ $netResult >= 0 ? 'surplus' : 'deficit' }}">
                    {{ number_format($netResult, 2, ',', '.') }}
                </td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        Laporan ini dicetak secara otomatis oleh Sistem ERP Perkumpulan Kaoem Telapak.
    </div>
</body>
</html>
