<?php

namespace App\Services\Budget;

use App\Models\Master\Activity;
use App\Models\Master\BudgetCategory;
use App\Models\Master\BudgetLine;
use App\Models\Master\ChartOfAccount;
use App\Models\Master\Currency;
use App\Models\Master\FiscalYear;
use App\Models\Master\GrantAgreement;
use App\Models\Master\Project;
use App\Models\Master\UnitOfMeasure;
use App\Services\Master\MasterExportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BudgetLineImportService
{
    public function templateColumns(): array
    {
        return [
            'relation_no',
            'project_code',
            'grant_no',
            'fiscal_year',
            'budget_category',
            'sub_output_code',
            'coa_code',
            'line_code',
            'description',
            'proposal_period',
            'currency',
            'exchange_rate',
            'unit_of_measure',
            'quantity',
            'unit_price',
            'total_amount',
            'activity_code',
            'is_active',
        ];
    }

    public function downloadTemplate(string $format, MasterExportService $exportService)
    {
        $normalized = strtolower(trim($format));
        if ($normalized === 'csv') {
            $headers = $exportService->templateHeaders($this->templateColumns());

            return new StreamedResponse(function () use ($headers) {
                $handle = fopen('php://output', 'w');
                fprintf($handle, "\xEF\xBB\xBF");
                fputcsv($handle, $headers);
                fclose($handle);
            }, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="budgetline-template.csv"',
                'Pragma' => 'no-cache',
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                'Expires' => '0',
            ]);
        }

        return $exportService->template('BudgetLine', $this->templateColumns());
    }

    public function import(UploadedFile $file, array $defaults = []): array
    {
        $rows = $this->readRows($file);
        $isQuickBooks = $this->isQuickBooksOverview($rows);
        $parsed = $isQuickBooks ? $this->parseQuickBooksRows($rows) : $this->parseFlatTemplateRows($rows);

        if (empty($parsed['items'])) {
            return [
                'created' => 0,
                'skipped' => 0,
                'format' => $isQuickBooks ? 'quickbooks_budget_overview' : 'standard_template',
                'errors' => [['row' => 1, 'message' => 'Tidak ada baris Budget Line yang dapat diimport dari file.']],
            ];
        }

        $created = 0;
        $skipped = 0;
        $errors = [];

        foreach ($parsed['items'] as $item) {
            $rowNumber = $item['row'];
            $result = $this->importSingleItem($item, $defaults, $parsed['meta'], $isQuickBooks);

            if ($result['ok']) {
                $created++;
            } else {
                $skipped++;
                $errors[] = ['row' => $rowNumber, 'message' => $result['message']];
            }
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'format' => $isQuickBooks ? 'quickbooks_budget_overview' : 'standard_template',
            'detected_project' => $parsed['meta']['project_hint'] ?? null,
            'errors' => $errors,
        ];
    }

    private function readRows(UploadedFile $file): array
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if (in_array($ext, ['csv', 'txt'], true)) {
            return $this->readCsvRows($file->getRealPath());
        }

        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();

        return $sheet->toArray(null, true, true, false);
    }

    private function readCsvRows(string $path): array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            return [];
        }

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = preg_split('/\r\n|\n|\r/', $content);
        $sample = implode("\n", array_slice($lines, 0, 5));
        $delimiter = substr_count($sample, ';') > substr_count($sample, ',') ? ';' : ',';

        $rows = [];
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    private function isQuickBooksOverview(array $rows): bool
    {
        foreach (array_slice($rows, 0, 15) as $row) {
            $col0 = trim((string) ($row[0] ?? ''));
            $col1 = trim((string) ($row[1] ?? ''));
            if (stripos($col0, 'Budget Overview:') === 0) {
                return true;
            }
            if (strcasecmp($col1, 'Total') === 0 && $col0 === '') {
                return true;
            }
        }

        return false;
    }

    private function parseQuickBooksRows(array $rows): array
    {
        $projectHint = null;
        $fiscalYearHint = null;

        foreach (array_slice($rows, 0, 6) as $row) {
            $col0 = trim((string) ($row[0] ?? ''));
            if (preg_match('/Budget Overview:\s*([^\-\n]+)/i', $col0, $match)) {
                $projectHint = trim($match[1]);
            }
            if ($fiscalYearHint === null && preg_match('/\b(20\d{2})\b/', $col0, $yearMatch)) {
                $fiscalYearHint = $yearMatch[1];
            }
        }

        $items = [];
        $currentCategory = null;
        $currentSubOutput = null;

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 1;
            $rawLabel = (string) ($row[0] ?? '');
            $trimmedLabel = trim($rawLabel);
            if ($trimmedLabel === '') {
                continue;
            }

            if (preg_match('/^total\b/i', $trimmedLabel) || strcasecmp($trimmedLabel, 'TOTAL') === 0) {
                continue;
            }

            if (! preg_match('/^(\d+(?:\.\d+)*)\.?\s+(.+)$/', $trimmedLabel, $labelMatch)) {
                continue;
            }

            $code = $labelMatch[1];
            $name = trim($labelMatch[2]);
            $amountRaw = $this->firstAmountCell($row);
            $leadingSpaces = strlen($rawLabel) - strlen(ltrim($rawLabel));

            if ($amountRaw === null) {
                $dotDepth = substr_count($code, '.');
                if ($leadingSpaces === 0 && $dotDepth === 0 && ! $this->looksLikeBudgetCategory($name)) {
                    $projectHint = $name;
                    continue;
                }

                if ($dotDepth === 0) {
                    $currentCategory = $name;
                    $currentSubOutput = "{$code}. {$name}";
                } else {
                    $currentSubOutput = "{$code} {$name}";
                }
                continue;
            }

            $parsedAmount = $this->parseAmount($amountRaw);
            if ($parsedAmount === null) {
                continue;
            }

            $items[] = [
                'row' => $rowNumber,
                'line_code' => $code,
                'description' => Str::limit($name, 255, ''),
                'budget_category' => $currentCategory ?: $name,
                'sub_output_code' => Str::limit($currentSubOutput ?: "{$code} {$name}", 50, ''),
                'total_amount' => abs($parsedAmount),
                'unit_price' => abs($parsedAmount),
                'quantity' => 1,
                'proposal_period' => 'annual',
                'is_active' => true,
            ];
        }

        return [
            'items' => $items,
            'meta' => [
                'project_hint' => $projectHint,
                'fiscal_year_hint' => $fiscalYearHint,
            ],
        ];
    }

    private function parseFlatTemplateRows(array $rows): array
    {
        $nonEmpty = array_values(array_filter($rows, function ($row) {
            if (! is_array($row)) {
                return false;
            }
            foreach ($row as $cell) {
                if (trim((string) $cell) !== '') {
                    return true;
                }
            }
            return false;
        }));

        if (count($nonEmpty) < 2) {
            return ['items' => [], 'meta' => []];
        }

        $rawHeaders = array_map(fn ($h) => $this->normalizeHeaderKey((string) $h), $nonEmpty[0]);
        $items = [];

        foreach (array_slice($nonEmpty, 1) as $offset => $values) {
            $rowNumber = $offset + 2;
            $assoc = [];
            foreach ($rawHeaders as $colIndex => $key) {
                if ($key === '') {
                    continue;
                }
                $assoc[$key] = isset($values[$colIndex]) ? trim((string) $values[$colIndex]) : '';
            }

            $lineCode = (string) ($assoc['line_code'] ?? $assoc['code'] ?? '');
            $description = (string) ($assoc['description'] ?? $assoc['name'] ?? '');
            if (preg_match('/^total\b/i', trim($lineCode)) || preg_match('/^total\b/i', trim($description))) {
                continue;
            }

            $assoc['row'] = $rowNumber;
            $items[] = $assoc;
        }

        return ['items' => $items, 'meta' => []];
    }

    private function importSingleItem(array $row, array $defaults, array $meta, bool $isQuickBooks): array
    {
        $lineCode = trim((string) ($row['line_code'] ?? $row['code'] ?? ''));
        $description = trim((string) ($row['description'] ?? $row['name'] ?? ''));

        if ($lineCode === '' || $description === '') {
            return ['ok' => false, 'message' => 'Line Code dan Description wajib diisi.'];
        }

        $lineCode = Str::limit($lineCode, 50, '');
        $description = Str::limit($description, 255, '');

        $fiscalYear = $this->resolveFiscalYear($row, $defaults, $meta);
        [$project, $grant, $relationNo, $relationError] = $this->resolveRelationContext($row, $defaults, $meta);

        if ($relationError !== null) {
            return ['ok' => false, 'message' => $relationError];
        }

        [$category, $categoryError] = $this->resolveBudgetCategory($row);
        if ($categoryError !== null) {
            return ['ok' => false, 'message' => $categoryError];
        }

        [$coa, $coaError] = $this->resolveChartOfAccount($row, $defaults, $category);
        if ($coaError !== null) {
            return ['ok' => false, 'message' => $coaError];
        }

        $quantity = $this->parseAmount($row['quantity'] ?? null);
        $quantity = ($quantity !== null && $quantity > 0) ? $quantity : 1.0;

        $totalRaw = $this->parseAmount($row['total_amount'] ?? null);
        $unitPriceRaw = $this->parseAmount($row['unit_price'] ?? null);

        if (! $isQuickBooks && (($totalRaw !== null && $totalRaw < 0) || ($unitPriceRaw !== null && $unitPriceRaw < 0))) {
            return ['ok' => false, 'message' => 'Nominal unit_price / total_amount tidak boleh bernilai negatif.'];
        }

        $totalAmount = $totalRaw !== null
            ? abs($totalRaw)
            : ($unitPriceRaw !== null ? round(abs($unitPriceRaw) * $quantity, 2) : 0.0);
        $unitPrice = $unitPriceRaw !== null
            ? abs($unitPriceRaw)
            : round($totalAmount / max($quantity, 1.0), 2);

        $currency = $this->resolveCurrency($row, $project, $grant);
        $uom = $this->resolveUnitOfMeasure($row);
        $activity = $this->resolveActivity($row, $project);

        $duplicateExists = BudgetLine::query()
            ->withoutGlobalScope('fiscal_year_context')
            ->where('line_code', $lineCode)
            ->when($project?->id, fn ($q, $projectId) => $q->where('project_id', $projectId))
            ->when(! $project?->id && $grant?->id, fn ($q, $grantId) => $q->where('grant_agreement_id', $grantId))
            ->exists();

        if ($duplicateExists) {
            return ['ok' => false, 'message' => "Duplicate line_code {$lineCode} already exists."];
        }

        $subOutput = trim((string) ($row['sub_output_code'] ?? ''));
        $proposalPeriod = strtolower(trim((string) ($row['proposal_period'] ?? 'annual')));
        if (! in_array($proposalPeriod, ['monthly', 'quarterly', 'semester', 'annual'], true)) {
            $proposalPeriod = 'annual';
        }

        $exchangeRate = $this->parseAmount($row['exchange_rate'] ?? null);
        $exchangeRate = ($exchangeRate !== null && $exchangeRate > 0) ? $exchangeRate : 1.0;

        $isActiveRaw = $row['is_active'] ?? ($row['active'] ?? true);
        $isActive = $this->parseBoolean($isActiveRaw, true);

        DB::transaction(function () use (
            $fiscalYear,
            $grant,
            $project,
            $relationNo,
            $activity,
            $subOutput,
            $category,
            $lineCode,
            $description,
            $coa,
            $proposalPeriod,
            $currency,
            $exchangeRate,
            $uom,
            $unitPrice,
            $quantity,
            $totalAmount,
            $isActive
        ) {
            BudgetLine::create([
                'fiscal_year_id' => $fiscalYear?->id,
                'grant_agreement_id' => $grant?->id,
                'project_id' => $project?->id,
                'relation_no' => $relationNo,
                'activity_id' => $activity?->id,
                'sub_output_code' => $subOutput !== '' ? Str::limit($subOutput, 50, '') : null,
                'budget_category_id' => $category->id,
                'line_code' => $lineCode,
                'description' => $description,
                'cost_type' => $coa?->code,
                'proposal_period' => $proposalPeriod,
                'currency_id' => $currency?->id,
                'exchange_rate' => $exchangeRate,
                'unit_of_measure_id' => $uom?->id,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'total_amount' => $totalAmount,
                'gl_account_id' => $coa?->id,
                'is_active' => $isActive,
            ]);
        });

        return ['ok' => true, 'message' => null];
    }

    private function resolveFiscalYear(array $row, array $defaults, array $meta): ?FiscalYear
    {
        $id = $row['fiscal_year_id'] ?? ($defaults['fiscal_year_id'] ?? null);
        if (! empty($id)) {
            $found = FiscalYear::query()->find($id);
            if ($found) {
                return $found;
            }
        }

        $hint = trim((string) ($row['fiscal_year'] ?? ($meta['fiscal_year_hint'] ?? '')));
        if ($hint !== '') {
            $yearDigits = preg_replace('/[^0-9]/', '', $hint);
            $found = FiscalYear::query()
                ->where('name', 'like', "%{$hint}%")
                ->when($yearDigits !== '', fn ($q) => $q->orWhere('year', (int) $yearDigits)->orWhereYear('start_date', $yearDigits))
                ->first();
            if ($found) {
                return $found;
            }
        }

        return FiscalYear::query()->where('is_active', true)->orderByDesc('start_date')->first()
            ?: FiscalYear::query()->orderByDesc('start_date')->first();
    }

    private function resolveRelationContext(array $row, array $defaults, array $meta): array
    {
        $rowRelation = trim((string) ($row['relation_no'] ?? ($row['nomor_relasi'] ?? '')));
        $defaultRelation = trim((string) ($defaults['relation_no'] ?? ''));

        if ($rowRelation !== '' && $defaultRelation !== '' && strcasecmp($rowRelation, $defaultRelation) !== 0) {
            return [null, null, null, 'Nomor Relasi baris tidak sesuai dengan scope Donor & Grant aktif.'];
        }

        $project = null;
        if (! empty($row['project_id'])) {
            $project = Project::query()->with('grantAgreement')->find($row['project_id']);
            if (! $project) {
                return [null, null, null, "Project ID {$row['project_id']} tidak ditemukan."];
            }
        } elseif (! empty($row['project_code'])) {
            $code = trim((string) $row['project_code']);
            $project = Project::query()->with('grantAgreement')->where('code', $code)->orWhere('name', $code)->first();
            if (! $project) {
                return [null, null, null, "Project Code {$code} tidak ditemukan."];
            }
        } elseif (! empty($defaults['project_id'])) {
            $project = Project::query()->with('grantAgreement')->find($defaults['project_id']);
        } elseif ($rowRelation !== '') {
            $project = Project::query()->with('grantAgreement')->where('relation_no', $rowRelation)->first();
        } elseif ($defaultRelation !== '') {
            $project = Project::query()->with('grantAgreement')->where('relation_no', $defaultRelation)->first();
        } elseif (! empty($meta['project_hint'])) {
            $hint = trim((string) $meta['project_hint']);
            $project = Project::query()->with('grantAgreement')
                ->where('code', $hint)
                ->orWhere('name', 'like', "%{$hint}%")
                ->first();
        }

        $grant = null;
        if (! empty($row['grant_agreement_id'])) {
            $grant = GrantAgreement::query()->withoutGlobalScope('fiscal_year_context')->find($row['grant_agreement_id']);
            if (! $grant) {
                return [null, null, null, "Grant Agreement ID {$row['grant_agreement_id']} tidak ditemukan."];
            }
        } elseif (! empty($row['grant_no'])) {
            $grantNo = trim((string) $row['grant_no']);
            $grant = GrantAgreement::query()->withoutGlobalScope('fiscal_year_context')
                ->where('grant_no', $grantNo)
                ->orWhere('agreement_no', $grantNo)
                ->first();
            if (! $grant) {
                return [null, null, null, "Grant No {$grantNo} tidak ditemukan."];
            }
        } elseif (! empty($defaults['grant_agreement_id'])) {
            $grant = GrantAgreement::query()->withoutGlobalScope('fiscal_year_context')->find($defaults['grant_agreement_id']);
        } elseif ($project?->grantAgreement) {
            $grant = $project->grantAgreement;
        } elseif ($rowRelation !== '') {
            $grant = GrantAgreement::query()->withoutGlobalScope('fiscal_year_context')->where('relation_no', $rowRelation)->first();
        } elseif ($defaultRelation !== '') {
            $grant = GrantAgreement::query()->withoutGlobalScope('fiscal_year_context')->where('relation_no', $defaultRelation)->first();
        }

        if ($project && $grant && $project->grant_agreement_id && (int) $project->grant_agreement_id !== (int) $grant->id) {
            return [null, null, null, 'Grant Agreement tidak sesuai dengan Project.'];
        }

        $expectedRelationNo = $project?->relation_no ?: $project?->grantAgreement?->relation_no ?: $grant?->relation_no;
        $requestedRelationNo = $rowRelation !== '' ? $rowRelation : $defaultRelation;

        if ($requestedRelationNo !== '' && $expectedRelationNo && strcasecmp($expectedRelationNo, $requestedRelationNo) !== 0) {
            return [null, null, null, 'Nomor Relasi tidak sesuai dengan Project atau Grant Agreement.'];
        }

        if (! $project && $grant) {
            $project = $grant->projects()->first();
        }

        $resolvedRelation = $expectedRelationNo ?: ($requestedRelationNo !== '' ? $requestedRelationNo : null);

        return [$project, $grant, $resolvedRelation, null];
    }

    private function resolveBudgetCategory(array $row): array
    {
        if (! empty($row['budget_category_id'])) {
            $category = BudgetCategory::query()->find($row['budget_category_id']);
            return $category ? [$category, null] : [null, "Budget Category ID {$row['budget_category_id']} tidak ditemukan."];
        }

        $label = trim((string) ($row['budget_category'] ?? ''));
        $categories = BudgetCategory::query()->where('is_active', true)->get();
        if ($categories->isEmpty()) {
            $categories = BudgetCategory::query()->get();
        }
        if ($categories->isEmpty()) {
            return [null, 'Master Budget Category masih kosong.'];
        }

        if ($label !== '') {
            $exact = $categories->first(fn (BudgetCategory $c) => strcasecmp($c->code, $label) === 0 || strcasecmp($c->name, $label) === 0);
            if ($exact) {
                return [$exact, null];
            }

            $lower = strtolower($label);
            $partial = $categories->first(fn (BudgetCategory $c) => str_contains(strtolower($c->name), $lower) || str_contains($lower, strtolower($c->name)));
            if ($partial) {
                return [$partial, null];
            }

            $mappedCode = match (true) {
                str_contains($lower, 'person') || str_contains($lower, 'salar') || str_contains($lower, 'staff') => 'BCAT-100',
                str_contains($lower, 'activit') || str_contains($lower, 'program') || str_contains($lower, 'workshop') => 'BCAT-200',
                str_contains($lower, 'overhead') || str_contains($lower, 'office') || str_contains($lower, 'travel') || str_contains($lower, 'admin') => 'BCAT-300',
                default => null,
            };

            if ($mappedCode) {
                $mapped = $categories->firstWhere('code', $mappedCode);
                if ($mapped) {
                    return [$mapped, null];
                }
            }
        }

        return [$categories->first(), null];
    }

    private function resolveChartOfAccount(array $row, array $defaults, BudgetCategory $category): array
    {
        $explicitCode = trim((string) ($row['coa_code'] ?? ($row['co_a_code'] ?? '')));
        if ($explicitCode !== '') {
            $coa = ChartOfAccount::query()
                ->where('code', $explicitCode)
                ->where('is_active', true)
                ->where('is_header', false)
                ->where('account_type', 'expense')
                ->first();

            return $coa
                ? [$coa, null]
                : [null, "CoA Code {$explicitCode} does not exist or is not an active expense account."];
        }

        if (! empty($row['gl_account_id'])) {
            $coa = ChartOfAccount::query()
                ->whereKey($row['gl_account_id'])
                ->where('is_active', true)
                ->where('account_type', 'expense')
                ->first();

            return $coa
                ? [$coa, null]
                : [null, "GL Account ID {$row['gl_account_id']} tidak ditemukan atau bukan akun expense aktif."];
        }

        $defaultCode = trim((string) ($defaults['coa_code'] ?? ''));
        if ($defaultCode !== '') {
            $coa = ChartOfAccount::query()
                ->where('code', $defaultCode)
                ->where('is_active', true)
                ->where('is_header', false)
                ->where('account_type', 'expense')
                ->first();
            if ($coa) {
                return [$coa, null];
            }
        }

        if (! empty($defaults['gl_account_id'])) {
            $coa = ChartOfAccount::query()
                ->whereKey($defaults['gl_account_id'])
                ->where('is_active', true)
                ->where('account_type', 'expense')
                ->first();
            if ($coa) {
                return [$coa, null];
            }
        }

        $categoryCoaMap = ['BCAT-100' => '5100', 'BCAT-200' => '5200', 'BCAT-300' => '5300'];
        $targetCode = $categoryCoaMap[$category->code] ?? '5200';

        $coa = ChartOfAccount::query()
            ->where('code', $targetCode)
            ->where('is_active', true)
            ->where('is_header', false)
            ->where('account_type', 'expense')
            ->first()
            ?: ChartOfAccount::query()
                ->where('is_active', true)
                ->where('is_header', false)
                ->where('account_type', 'expense')
                ->orderBy('code')
                ->first();

        return [$coa, null];
    }

    private function resolveCurrency(array $row, ?Project $project, ?GrantAgreement $grant): ?Currency
    {
        if (! empty($row['currency_id'])) {
            return Currency::query()->find($row['currency_id']);
        }

        $code = trim((string) ($row['currency'] ?? ($row['currency_code'] ?? '')));
        if ($code !== '') {
            $found = Currency::query()->where('code', strtoupper($code))->first();
            if ($found) {
                return $found;
            }
        }

        if ($grant?->currency_id) {
            return Currency::query()->find($grant->currency_id);
        }

        if ($project?->budget_currency_id) {
            return Currency::query()->find($project->budget_currency_id);
        }

        return Currency::query()->where('is_base_currency', true)->first()
            ?: Currency::query()->where('code', 'IDR')->first();
    }

    private function resolveUnitOfMeasure(array $row): ?UnitOfMeasure
    {
        if (! empty($row['unit_of_measure_id'])) {
            return UnitOfMeasure::query()->find($row['unit_of_measure_id']);
        }

        $label = trim((string) ($row['unit_of_measure'] ?? ''));
        if ($label === '') {
            return null;
        }

        return UnitOfMeasure::query()
            ->where('code', $label)
            ->orWhere('name', $label)
            ->first();
    }

    private function resolveActivity(array $row, ?Project $project): ?Activity
    {
        if (! empty($row['activity_id'])) {
            return Activity::query()->find($row['activity_id']);
        }

        $code = trim((string) ($row['activity_code'] ?? ''));
        if ($code === '') {
            return null;
        }

        return Activity::query()
            ->where('code', $code)
            ->when($project?->id, fn ($q, $projectId) => $q->where('project_id', $projectId))
            ->first();
    }

    private function firstAmountCell(array $row): ?string
    {
        foreach (array_slice($row, 1) as $cell) {
            $val = trim((string) ($cell ?? ''));
            if ($val !== '') {
                return $val;
            }
        }

        return null;
    }

    private function parseAmount($raw): ?float
    {
        if ($raw === null) {
            return null;
        }
        $str = trim((string) $raw);
        if ($str === '') {
            return null;
        }

        $isNegative = str_contains($str, '-') || (str_starts_with($str, '(') && str_ends_with($str, ')'));
        $clean = preg_replace('/[^0-9.,]/', '', $str);
        if ($clean === '') {
            return null;
        }

        if (str_contains($clean, ',') && str_contains($clean, '.')) {
            if (strrpos($clean, '.') > strrpos($clean, ',')) {
                $clean = str_replace(',', '', $clean);
            } else {
                $clean = str_replace('.', '', $clean);
                $clean = str_replace(',', '.', $clean);
            }
        } elseif (str_contains($clean, ',')) {
            $parts = explode(',', $clean);
            if (count($parts) === 2 && strlen($parts[1]) <= 2) {
                $clean = $parts[0].'.'.$parts[1];
            } else {
                $clean = str_replace(',', '', $clean);
            }
        }

        $value = (float) $clean;

        return $isNegative ? -$value : $value;
    }

    private function looksLikeBudgetCategory(string $name): bool
    {
        $lower = strtolower($name);

        return str_contains($lower, 'person')
            || str_contains($lower, 'activit')
            || str_contains($lower, 'overhead')
            || str_contains($lower, 'travel')
            || str_contains($lower, 'admin')
            || str_contains($lower, 'operat');
    }

    private function normalizeHeaderKey(string $header): string
    {
        $clean = str_replace(['(ID)', '(id)'], '_id', trim($header));
        $snake = Str::snake(preg_replace('/[^A-Za-z0-9_]+/', '_', $clean));
        $snake = preg_replace('/_+/', '_', trim($snake, '_'));

        return match ($snake) {
            'co_a_code' => 'coa_code',
            'active' => 'is_active',
            'budget_category_id_id' => 'budget_category_id',
            default => $snake,
        };
    }

    private function parseBoolean($value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        $normalized = strtolower(trim((string) $value));
        if ($normalized === '') {
            return $default;
        }

        return ! in_array($normalized, ['0', 'false', 'no', 'tidak', 'inactive', 'nonaktif'], true);
    }
}
