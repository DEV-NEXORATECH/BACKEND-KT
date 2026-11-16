<?php

namespace App\Services\Master;

use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MasterExportService
{
    public function attachment(string $entityName, $collection, string $format): array
    {
        $format = strtolower($format);
        $format = $format === 'google_sheets' ? 'csv' : $format;
        $safeName = strtolower(preg_replace('/[^A-Za-z0-9_-]/', '-', $entityName));
        $base = tempnam(sys_get_temp_dir(), 'kt-mail-');

        if ($format === 'pdf') {
            $path = $base . '.pdf';
            file_put_contents($path, Pdf::loadView('exports.master.template', [
                'entityName' => $entityName,
                'collection' => $collection,
                'timestamp' => now()->translatedFormat('d F Y H:i:s'),
            ])->setPaper('a4', 'landscape')->output());
            @unlink($base);
            return [$path, "{$safeName}.pdf", 'application/pdf'];
        }

        if (in_array($format, ['xlsx', 'excel'], true)) {
            $path = $base . '.xlsx';
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $rows = [];
            if ($collection->isNotEmpty()) {
                $first = $this->flattenArray($collection->first()->toArray());
                $rows[] = array_map(fn (string $key): string => $this->displayHeader($key), array_keys($first));
                foreach ($collection as $item) $rows[] = array_values($this->flattenArray($item->toArray()));
            } else $rows[] = ['No data'];
            $sheet->fromArray($rows, null, 'A1');
            $writer = new Xlsx($spreadsheet);
            $writer->save($path);
            @unlink($base);
            return [$path, "{$safeName}.xlsx", 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        }

        $path = $base . '.csv';
        $handle = fopen($path, 'w');
        fprintf($handle, "\xEF\xBB\xBF");
        if ($collection->isNotEmpty()) {
            $first = $this->flattenArray($collection->first()->toArray());
            fputcsv($handle, array_map(fn (string $key): string => $this->displayHeader($key), array_keys($first)));
            foreach ($collection as $item) fputcsv($handle, array_values($this->flattenArray($item->toArray())));
        } else fputcsv($handle, ['No data']);
        fclose($handle);
        @unlink($base);
        return [$path, "{$safeName}.csv", 'text/csv'];
    }
    public function template(string $entityName, array $columns, array $headerOverrides = [])
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Input Data');
        $displayColumns = $this->templateHeaders($columns, $headerOverrides);
        $isCoaTemplate = $entityName === 'ChartOfAccount';
        $isBankTemplate = $entityName === 'BankAccount';
        if ($isCoaTemplate || $isBankTemplate) $displayColumns[] = 'Import Note';
        $sheet->fromArray([$displayColumns], null, 'A1');
        if ($isCoaTemplate) {
            $sheet->fromArray([
                ['10000', 'BANK AND CASH', 'asset', 'Cash and cash equivalents', 'debit', 0, now()->startOfYear()->toDateString(), '', 'Active', 'CONTOH BANK UTAMA — hapus semua baris contoh sebelum import.'],
                ['10200', 'BANK BNI', 'asset', 'Bank', 'debit', 0, now()->startOfYear()->toDateString(), '10000', 'Active', 'CONTOH SUB-AKUN — Parent Account diisi dengan Account Number akun induk.'],
                ['10210', 'BNI OPERASIONAL', 'asset', 'Bank', 'debit', 0, now()->startOfYear()->toDateString(), '10200', 'Active', 'CONTOH SUB DALAM SUB — urutkan parent lebih dulu; hapus baris contoh sebelum import.'],
            ], null, 'A2');
        } elseif ($isBankTemplate) {
            $sheet->fromArray([
                ['Bank BNI', '0000000001', 'Kaoem Telapak Operasional', 'PROJECT-A', '10210', 'IDR', 'BNI-IDJA', 0, now()->startOfYear()->toDateString(), 'KT', now()->year, 'Active', 'CONTOH REKENING + MAPPING PROJECT A — hapus semua baris contoh sebelum import.'],
                ['Bank BNI', '0000000001', 'Kaoem Telapak Operasional', 'PROJECT-B', '10211', 'IDR', 'BNI-IDJA', 0, now()->startOfYear()->toDateString(), 'KT', now()->year, 'Active', 'NOMOR REKENING SAMA diperbolehkan jika Project Code berbeda; baris ini menambah mapping Project B → COA 10211.'],
            ], null, 'A2');
        }
        $lastColumn = $sheet->getHighestColumn();
        $headerStyle = $sheet->getStyle("A1:{$lastColumn}1");
        $headerStyle->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $headerStyle->getFill()->setFillType('solid')->getStartColor()->setARGB('FF1F4E78');
        $headerStyle->getAlignment()->setHorizontal('center');
        $headerStyle->getAlignment()->setVertical('center');
        $headerStyle->getAlignment()->setWrapText(true);
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}1");
        $columnCount = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($lastColumn);
        for ($column = 1; $column <= $columnCount; $column++) {
            $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column);
            $sheet->getColumnDimension($letter)->setAutoSize(true);
        }
        if ($isCoaTemplate) {
            $sheet->getStyle("A2:{$lastColumn}2")->getFill()->setFillType('solid')->getStartColor()->setARGB('FFDCE6F1');
            $sheet->getStyle("A3:{$lastColumn}3")->getFill()->setFillType('solid')->getStartColor()->setARGB('FFE2F0D9');
            $sheet->getStyle("A4:{$lastColumn}4")->getFill()->setFillType('solid')->getStartColor()->setARGB('FFFFF2CC');
        } elseif ($isBankTemplate) {
            $sheet->getStyle("A2:{$lastColumn}2")->getFill()->setFillType('solid')->getStartColor()->setARGB('FFDCE6F1');
            $sheet->getStyle("A3:{$lastColumn}3")->getFill()->setFillType('solid')->getStartColor()->setARGB('FFE2F0D9');
        }
        if ($isCoaTemplate || $isBankTemplate) {
            $sheet->getColumnDimension($lastColumn)->setWidth(72);
            $sheet->getStyle("{$lastColumn}1:{$lastColumn}".($isCoaTemplate ? '4' : '3'))->getAlignment()->setWrapText(true);
        }

        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('Instructions');
        $instructionRows = [
            ['Template', $entityName],
            ['How to use', 'Enter data in the Input Data sheet. Do not change the column order. Add one row for each new record.'],
            ['Format import', 'Excel (.xlsx/.xls) atau CSV UTF-8. Kolom tampilan yang mudah dibaca dan nama field API sama-sama diterima saat import.'],
            ['Relasi', 'Kolom bertanda (ID) diisi dengan ID data terkait.'],
            ['Contoh data', ($isCoaTemplate || $isBankTemplate) ? 'Baris berwarna hanyalah contoh/dummy. Hapus seluruh baris contoh sebelum mengisi dan mengimpor data asli.' : 'Tidak ada baris contoh.'],
            ['', ''],
            ['Kolom pada template', 'Field import'],
        ];
        foreach ($columns as $column) {
            $instructionRows[] = [$headerOverrides[$column] ?? $this->displayHeader($column), $column];
        }
        $instructions->fromArray($instructionRows, null, 'A1');
        $instructions->getStyle('A1:A7')->getFont()->setBold(true);
        $instructions->getStyle('A7:B7')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $instructions->getStyle('A7:B7')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF1F4E78');
        $instructions->getColumnDimension('A')->setWidth(24);
        $instructions->getColumnDimension('B')->setWidth(110);
        $instructions->getStyle('B1:B3')->getAlignment()->setWrapText(true);

        $writer = new Xlsx($spreadsheet);
        $temp = tempnam(sys_get_temp_dir(), 'kt-template-');
        $writer->save($temp);
        return response()->download($temp, strtolower($entityName).'-template.xlsx')->deleteFileAfterSend(true);
    }

    public function templateHeaders(array $columns, array $headerOverrides = []): array
    {
        return array_map(fn (string $column): string => $headerOverrides[$column] ?? $this->displayHeader($column), $columns);
    }

    private function displayHeader(string $column): string
    {
        if ($column === 'is_active') return 'Active';
        if (str_ends_with($column, '_id')) {
            return ucwords(str_replace('_', ' ', substr($column, 0, -3))).' (ID)';
        }
        return ucwords(str_replace('_', ' ', $column));
    }

    public function export(string $entityName, $collection, string $format = 'csv')
    {
        $format = strtolower($format) === 'google_sheets' ? 'csv' : $format;
        $filename = strtolower($entityName) . '_' . date('Ymd_His');

        if ($format === 'pdf') {
            return $this->exportPdf($entityName, $collection, $filename);
        }

        if (in_array($format, ['xlsx', 'excel'], true)) {
            return $this->exportXlsx($entityName, $collection, $filename);
        }

        // Default CSV Streaming
        return $this->exportCsv($entityName, $collection, $filename);
    }

    private function exportCsv(string $entityName, $collection, string $filename): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($collection) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel compatibility

            if ($collection->isNotEmpty()) {
                $first = $collection->first()->toArray();
                $flatKeys = array_keys($this->flattenArray($first));
                fputcsv($handle, array_map(fn (string $key): string => $this->displayHeader($key), $flatKeys));

                foreach ($collection as $item) {
                    $row = $this->flattenArray($item->toArray());
                    fputcsv($handle, array_values($row));
                }
            } else {
                fputcsv($handle, ['No data available']);
            }

            fclose($handle);
        }, 200, $headers);
    }

    private function exportPdf(string $entityName, $collection, string $filename)
    {
        $pdf = Pdf::loadView('exports.master.template', [
            'entityName' => $entityName,
            'collection' => $collection,
            'timestamp' => now()->translatedFormat('d F Y H:i:s'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download("{$filename}.pdf");
    }

    private function exportXlsx(string $entityName, $collection, string $filename)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr(preg_replace('/[^A-Za-z0-9 ]/', '', $entityName), 0, 31) ?: 'Report');
        $rows = [];
        if ($collection->isNotEmpty()) {
            $first = $this->flattenArray($collection->first()->toArray());
            $flatKeys = array_keys($first);
            $rows[] = array_map(fn (string $key): string => $this->displayHeader($key), $flatKeys);
            foreach ($collection as $item) $rows[] = array_values($this->flattenArray($item->toArray()));
        } else {
            $rows[] = ['No data available'];
        }
        $sheet->fromArray($rows, null, 'A1');
        $lastColumn = $sheet->getHighestColumn();
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
        $sheet->freezePane('A2');
        foreach (range(1, $sheet->getHighestColumn() ? \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($lastColumn) : 1) as $column) {
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
        }
        $writer = new Xlsx($spreadsheet);
        $temp = tempnam(sys_get_temp_dir(), 'kt-export-');
        $writer->save($temp);
        return response()->download($temp, "{$filename}.xlsx")->deleteFileAfterSend(true);
    }

    public function flattenArray(array $array, string $prefix = ''): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            if (in_array($key, ['created_by', 'updated_by', 'deleted_by', 'deleted_at'])) {
                continue;
            }
            $newKey = $prefix === '' ? $key : "{$prefix}_{$key}";
            if (is_array($value)) {
                if (isset($value['name']) || isset($value['code']) || isset($value['title'])) {
                    $result[$newKey] = $value['name'] ?? $value['code'] ?? $value['title'];
                } else {
                    $result[$newKey] = json_encode($value);
                }
            } else {
                $result[$newKey] = $value;
            }
        }
        return $result;
    }
}
