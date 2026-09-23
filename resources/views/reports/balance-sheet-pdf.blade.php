<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Neraca (Balance Sheet)</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10pt; color: #222; }
        .header { text-align: center; border-bottom: 3px double #1a5632; padding-bottom: 12px; margin-bottom: 18px; }
        .header h1 { font-size: 16pt; color: #1a5632; margin-bottom: 2px; }
        .header h2 { font-size: 12pt; color: #333; font-weight: normal; margin-bottom: 4px; }
        .header p { font-size: 9pt; color: #666; }
        .meta { text-align: right; font-size: 9pt; color: #666; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th { background: #1a5632; color: #fff; text-align: left; padding: 6px 8px; font-size: 9pt; }
        td { border-bottom: 1px solid #ddd; padding: 5px 8px; font-size: 9pt; }
        .right { text-align: right; }
        .section-title { background: #e8f5e9; font-weight: bold; padding: 6px 8px; font-size: 10pt; color: #1a5632; }
        .subtotal { font-weight: bold; background: #f5f5f5; }
        .grand-total { font-weight: bold; background: #1a5632; color: #fff; }
        .footer { text-align: center; font-size: 8pt; color: #999; margin-top: 20px; border-top: 1px solid #ccc; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>PERKUMPULAN KAOEM TELAPAK</h1>
        <h2>NERACA (BALANCE SHEET)</h2>
        <p>Per Tanggal: {{ $as_of }}</p>
    </div>

    <div class="meta">
        Dicetak pada: {{ now()->format('d/m/Y H:i') }}
    </div>

    {{-- ASET --}}
    <table>
        <thead>
            <tr>
                <th style="width:15%">Kode Akun</th>
                <th style="width:55%">Nama Akun</th>
                <th class="right" style="width:30%">Saldo (IDR)</th>
            </tr>
        </thead>
        <tbody>
            <tr><td colspan="3" class="section-title">ASET</td></tr>
            @foreach ($data['assets'] as $row)
            <tr>
                <td>{{ $row['code'] }}</td>
                <td>{{ $row['name'] }}</td>
                <td class="right">{{ number_format($row['balance'], 2, ',', '.') }}</td>
            </tr>
            @endforeach
            <tr class="subtotal">
                <td colspan="2">Total Aset</td>
                <td class="right">{{ number_format($data['totals']['assets'], 2, ',', '.') }}</td>
            </tr>

            {{-- LIABILITAS --}}
            <tr><td colspan="3" class="section-title">LIABILITAS (KEWAJIBAN)</td></tr>
            @foreach ($data['liabilities'] as $row)
            <tr>
                <td>{{ $row['code'] }}</td>
                <td>{{ $row['name'] }}</td>
                <td class="right">{{ number_format($row['balance'], 2, ',', '.') }}</td>
            </tr>
            @endforeach
            <tr class="subtotal">
                <td colspan="2">Total Liabilitas</td>
                <td class="right">{{ number_format($data['totals']['liabilities'], 2, ',', '.') }}</td>
            </tr>

            {{-- EKUITAS --}}
            <tr><td colspan="3" class="section-title">EKUITAS (ASET NETO)</td></tr>
            @foreach ($data['equity'] as $row)
            <tr>
                <td>{{ $row['code'] }}</td>
                <td>{{ $row['name'] }}</td>
                <td class="right">{{ number_format($row['balance'], 2, ',', '.') }}</td>
            </tr>
            @endforeach
            <tr>
                <td></td>
                <td><em>Surplus/(Defisit) Periode Berjalan</em></td>
                <td class="right"><em>{{ number_format($data['totals']['current_result'], 2, ',', '.') }}</em></td>
            </tr>
            <tr class="subtotal">
                <td colspan="2">Total Ekuitas</td>
                <td class="right">{{ number_format($data['totals']['equity'] + $data['totals']['current_result'], 2, ',', '.') }}</td>
            </tr>

            {{-- TOTAL LIABILITAS + EKUITAS --}}
            <tr class="grand-total">
                <td colspan="2">TOTAL LIABILITAS + EKUITAS</td>
                <td class="right">{{ number_format($data['totals']['liabilities_and_equity'], 2, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    @if (isset($data['fixed_assets']))
    <p style="font-size: 9pt; color: #555;">
        <strong>Catatan Aset Tetap:</strong>
        Total Akuisisi: Rp {{ number_format($data['fixed_assets']['acquisition_cost'], 0, ',', '.') }},
        Akumulasi Penyusutan: Rp {{ number_format($data['fixed_assets']['accumulated_depreciation'], 0, ',', '.') }},
        Nilai Buku Bersih: Rp {{ number_format($data['fixed_assets']['net_book_value'], 0, ',', '.') }}
        ({{ $data['fixed_assets']['asset_count'] }} unit)
    </p>
    @endif

    <div class="footer">
        Laporan ini dicetak secara otomatis oleh Sistem ERP Perkumpulan Kaoem Telapak.
    </div>
</body>
</html>
