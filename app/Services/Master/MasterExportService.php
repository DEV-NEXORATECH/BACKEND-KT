<?php

namespace App\Services\Master;

use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MasterExportService
{
    public function template(string $entityName, array $columns)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Input Data');
        $displayColumns = $this->templateHeaders($columns);
        $sheet->fromArray([$displayColumns], null, 'A1');
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

        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('Instructions');
        $instructionRows = [
            ['Template', $entityName],
            ['Cara penggunaan', 'Isi data pada sheet Input Data. Jangan mengubah urutan kolom. Tambahkan satu baris untuk setiap data baru.'],
            ['Format import', 'Excel (.xlsx/.xls) atau CSV UTF-8. Kolom tampilan yang mudah dibaca dan nama field API sama-sama diterima saat import.'],
            ['Relasi', 'Kolom bertanda (ID) diisi dengan ID data terkait.'],
            ['', ''],
            ['Kolom pada template', 'Field import'],
        ];
        foreach ($columns as $column) {
            $instructionRows[] = [$this->displayHeader($column), $column];
        }
        $instructions->fromArray($instructionRows, null, 'A1');
        $instructions->getStyle('A1:A6')->getFont()->setBold(true);
        $instructions->getStyle('A6:B6')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $instructions->getStyle('A6:B6')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF1F4E78');
        $instructions->getColumnDimension('A')->setWidth(24);
        $instructions->getColumnDimension('B')->setWidth(110);
        $instructions->getStyle('B1:B3')->getAlignment()->setWrapText(true);

        $writer = new Xlsx($spreadsheet);
        $temp = tempnam(sys_get_temp_dir(), 'kt-template-');
        $writer->save($temp);
        return response()->download($temp, strtolower($entityName).'-template.xlsx')->deleteFileAfterSend(true);
    }

    public function templateHeaders(array $columns): array
    {
        return array_map(fn (string $column): string => $this->displayHeader($column), $columns);
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
                fputcsv($handle, $flatKeys);

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
            $rows[] = array_keys($first);
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

    private function flattenArray(array $array, string $prefix = ''): array
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
