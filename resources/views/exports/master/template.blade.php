<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Master Data - {{ $entityName }}</title>
    <style>
        @font-face { font-family: 'Montserrat'; font-style: normal; font-weight: 100 900; src: url('{{ public_path('fonts/Montserrat-Variable.ttf') }}') format('truetype'); }
        @page { size: A4 landscape; margin: 16mm 18mm; }
        * { box-sizing: border-box; }
        body { font-family: 'Montserrat', Arial, Helvetica, sans-serif; font-size: 9px; color: #000; margin: 0; }
        .header {
            text-align: left;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }
        .org-name { font-size: 16px; font-weight: bold; text-transform: uppercase; }
        .doc-title { font-size: 13px; font-weight: bold; margin-top: 4px; }
        .meta { font-size: 9px; color: #555; margin-top: 3px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #fff; color: #000; font-weight: bold; text-align: left; padding: 6px 8px; border-bottom: 1px solid #000; font-size: 9px; text-transform: uppercase; }
        td { padding: 5px 8px; border: 0; font-size: 9px; vertical-align: top; }
        tr:nth-child(even) { background: #fff; }
        .badge-active, .badge-inactive { color: #000; font-weight: bold; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; font-size: 8px; text-align: right; color: #555; border-top: 1px solid #bbb; padding-top: 4px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="org-name">KAOEM TELAPAK</div>
        <div class="doc-title">Master Data: {{ preg_replace('/(?<!\ )[A-Z]/', ' $0', $entityName) }}</div>
        <div class="meta">Generated at: {{ $timestamp }} | Total Records: {{ count($collection) }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 30px;">No</th>
                @if($collection->isNotEmpty())
                    @foreach(array_values(array_filter(array_keys($collection->first()->getAttributes()), fn ($col) => !in_array($col, ['id', 'created_by', 'updated_by', 'deleted_by', 'deleted_at', 'created_at', 'updated_at', 'is_active']))) as $col)
                        <th>{{ strtoupper(str_replace('_', ' ', $col)) }}</th>
                    @endforeach
                @else
                    <th>Data</th>
                @endif
                <th style="width: 50px;">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($collection as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    @foreach(array_values(array_filter(array_keys($item->getAttributes()), fn ($col) => !in_array($col, ['id', 'created_by', 'updated_by', 'deleted_by', 'deleted_at', 'created_at', 'updated_at', 'is_active']))) as $col)
                        <td>
                            @if($col === 'fiscal_year_id' && $item->relationLoaded('fiscalYear'))
                                {{ $item->fiscalYear?->year ?? $item->{$col} }}
                            @elseif(is_bool($item->{$col}))
                                {{ $item->{$col} ? 'Yes' : 'No' }}
                            @else
                                {{ $item->{$col} }}
                            @endif
                        </td>
                    @endforeach
                    <td>
                        <span class="{{ $item->is_active ? 'badge-active' : 'badge-inactive' }}">
                            {{ $item->is_active ? 'ACTIVE' : 'INACTIVE' }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center;">Tidak ada data ditemukan.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Kaoem Telapak Financial ERP &copy; {{ date('Y') }}
    </div>
</body>
</html>
