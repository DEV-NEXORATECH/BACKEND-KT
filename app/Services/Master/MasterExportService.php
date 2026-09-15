<?php

namespace App\Services\Master;

use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MasterExportService
{
    public function export(string $entityName, $collection, string $format = 'csv')
    {
        $filename = strtolower($entityName) . '_' . date('Ymd_His');

        if ($format === 'pdf') {
            return $this->exportPdf($entityName, $collection, $filename);
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