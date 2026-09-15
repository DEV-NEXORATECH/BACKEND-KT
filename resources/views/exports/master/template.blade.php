<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Master Data - {{ $entityName }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #333;
            margin: 15px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #004d40;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .org-name {
            font-size: 16px;
            font-weight: bold;
            color: #004d40;
            text-transform: uppercase;
        }
        .doc-title {
            font-size: 13px;
            font-weight: bold;
            margin-top: 4px;
            color: #263238;
        }
        .meta {
            font-size: 9px;
            color: #78909c;
            margin-top: 3px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th {
            background-color: #004d40;
            color: #ffffff;
            font-weight: bold;
            text-align: left;
            padding: 6px 8px;
            border: 1px solid #00332c;
            font-size: 9px;
            text-transform: uppercase;
        }
        td {
            padding: 5px 8px;
            border: 1px solid #cfd8dc;
            font-size: 9px;
        }
        tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .badge-active {
            color: #2e7d32;
            font-weight: bold;
        }
        .badge-inactive {
            color: #c62828;
            font-weight: bold;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            font-size: 8px;
            text-align: right;
            color: #90a4ae;
            border-top: 1px solid #eceff1;
            padding-top: 4px;
        }
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
                    @foreach(array_slice(array_keys($collection->first()->getAttributes()), 1, 6) as $col)
                        @if(!in_array($col, ['created_by', 'updated_by', 'deleted_by', 'deleted_at', 'created_at', 'updated_at']))
                            <th>{{ strtoupper(str_replace('_', ' ', $col)) }}</th>
                        @endif
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
                    @foreach(array_slice(array_keys($item->getAttributes()), 1, 6) as $col)
                        @if(!in_array($col, ['created_by', 'updated_by', 'deleted_by', 'deleted_at', 'created_at', 'updated_at']))
                            <td>{{ is_bool($item->{$col}) ? ($item->{$col} ? 'Yes' : 'No') : $item->{$col} }}</td>
                        @endif
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