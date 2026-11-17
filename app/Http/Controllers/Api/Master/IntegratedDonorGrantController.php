<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\Accounting\JournalLine;
use App\Models\Budget\BudgetCommitment;
use App\Models\Master\BudgetLine;
use App\Models\Master\Donor;
use App\Models\Master\FiscalYear;
use App\Models\Master\GrantAgreement;
use App\Models\Master\Project;
use App\Models\Master\Program;
use App\Models\Master\ProjectLogframe;
use App\Models\Master\ProjectWorkplan;
use App\Models\Master\BudgetCategory;
use App\Models\Master\ChartOfAccount;
use App\Models\Master\GrantReportingDeadline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class IntegratedDonorGrantController extends Controller
{
    private function resolveRelationNo(array $data, ?GrantAgreement $grant = null): string
    {
        $relationNo = trim((string) ($data['relation_no'] ?? $grant?->relation_no ?? ''));

        return $relationNo !== '' ? Str::upper($relationNo) : 'KT-REL-'.Str::upper((string) Str::uuid());
    }

    private function findDonorAcrossFiscalYears(int|string $donorId): Donor
    {
        return Donor::query()->withoutGlobalScope('fiscal_year_context')->findOrFail($donorId);
    }

    private function integratedRelations(): array
    {
        return [
            'grantAgreements' => fn ($query) => $query->withoutGlobalScope('fiscal_year_context'),
            'grantAgreements.fiscalYear',
            'grantAgreements.currency',
            'grantAgreements.fundingSource',
            'grantAgreements.bankAccount',
            'grantAgreements.reportingDeadlines',
            'grantAgreements.projects' => fn ($query) => $query->withoutGlobalScope('fiscal_year_context'),
            'grantAgreements.projects.fiscalYears',
            'grantAgreements.projects.program',
            'grantAgreements.projects.partners',
            'grantAgreements.projects.fundingSources',
            'grantAgreements.projects.logframes' => fn ($query) => $query->withoutGlobalScope('fiscal_year_context'),
            'grantAgreements.projects.workplans' => fn ($query) => $query->withoutGlobalScope('fiscal_year_context'),
            'grantAgreements.projects.budgetLines' => fn ($query) => $query->withoutGlobalScope('fiscal_year_context'),
        ];
    }

    public function show(int|string $donor)
    {
        $record = $this->findDonorAcrossFiscalYears($donor);

        return response()->json([
            'success' => true,
            'data' => $record->load($this->integratedRelations()),
        ]);
    }

    public function destroyProject(int|string $donor, int|string $project)
    {
        $donorModel = $this->findDonorAcrossFiscalYears($donor);
        $projectModel = Project::query()->withoutGlobalScope('fiscal_year_context')->findOrFail($project);
        $belongsToDonor = $donorModel->grantAgreements()
            ->withoutGlobalScope('fiscal_year_context')
            ->whereKey($projectModel->grant_agreement_id)
            ->exists();
        if (! $belongsToDonor) {
            return response()->json(['message' => 'Project tidak terhubung ke Funder ini.'], 404);
        }

        $projectModel->delete();
        return response()->json(['success' => true, 'message' => 'Project berhasil dihapus.']);
    }

    public function storeDraft(Request $request)
    {
        $data = $request->validate([
            'donor.id' => 'nullable|integer|exists:donors,id',
            'donor' => 'required|array',
            'donor.code' => 'required|string|max:30|unique:donors,code,' . ((int) $request->input('donor.id')),
            'donor.name' => 'required|string|max:150',
            'donor.type' => 'nullable|string|max:50',
            'donor.lead_applicant' => 'nullable|string|max:150',
            'donor.country' => 'nullable|string|max:100',
            'donor.contact_person' => 'nullable|string|max:100',
            'donor.email' => 'nullable|email|max:150',
            'donor.phone' => 'nullable|string|max:50',
        ]);

        $draftPayload = $request->except(['documents']);
        $donor = ! empty($data['donor']['id']) ? $this->findDonorAcrossFiscalYears($data['donor']['id']) : Donor::create(array_merge($data['donor'], [
            'type' => $data['donor']['type'] ?? 'Other',
            'status' => 'inactive',
            'is_active' => false,
        ]));
        $donor->update(['draft_payload' => $draftPayload, 'status' => 'inactive', 'is_active' => false]);

        return response()->json(['success' => true, 'data' => $donor, 'message' => 'Funder draft berhasil disimpan.'], 201);
    }

    public function export(Request $request)
    {
        $records = Donor::query()
            ->withoutGlobalScope('fiscal_year_context')
            ->with($this->integratedRelations())
            ->get();
        if ($request->query('format') === 'json') {
            $payload = $records->flatMap(function ($donor) {
                return $donor->grantAgreements->flatMap(function ($grant) use ($donor) {
                    $projects = $grant->projects->isEmpty() ? [null] : $grant->projects;
                    return collect($projects)->map(function ($project) use ($donor, $grant) {
                        return [
                            'relation_no' => $project?->relation_no ?? $grant->relation_no,
                            'donor' => $donor->only(['code', 'name', 'type', 'lead_applicant', 'country', 'contact_person', 'email', 'phone']),
                            'grant' => array_merge($grant->only(['grant_no', 'agreement_no', 'agreement_date', 'agreement_name', 'terms_conditions', 'reporting_period', 'donor_fiscal_calendar', 'grant_value', 'start_date', 'end_date', 'status']), ['currency_id' => $grant->currency_id, 'funding_source_id' => $grant->funding_source_id, 'bank_account_id' => $grant->bank_account_id]),
                            'programme' => $project?->program?->only(['id', 'code', 'name', 'objective']) ?? [],
                            'project' => $project ? array_merge($project->only(['code', 'name', 'program_id', 'theme', 'manager_name', 'start_date', 'end_date', 'total_budget']), ['partner_ids' => $project->partners->pluck('id')->values()->all(), 'funding_source_ids' => $project->fundingSources->pluck('id')->values()->all()]) : [],
                            'partners' => $project?->partners?->map->only(['id', 'code', 'name'])->values() ?? [],
                            'funding_sources' => $project?->fundingSources?->map->only(['id', 'code', 'name'])->values() ?? [],
                            'frameworks' => $project?->logframes?->map->only(['relation_no', 'level', 'code', 'description', 'indicator', 'baseline', 'target'])->values() ?? [],
                            'workplans' => $project?->workplans?->map->only(['relation_no', 'output_code', 'activity', 'start_date', 'end_date', 'status', 'baseline'])->values() ?? [],
                            'budgets' => $project?->budgetLines?->map->only(['relation_no', 'line_code', 'description', 'budget_category_id', 'total_amount'])->values() ?? [],
                            'deadlines' => $grant->reportingDeadlines->map->only(['report_type', 'period_label', 'due_date', 'status', 'notes', 'reminder_days'])->values(),
                        ];
                    });
                });
            })->values();
            return response()->json(['template_version' => 'donor-grant-integrated-v1', 'records' => $payload]);
        }
        if ($request->query('format') === 'xlsx') {
            $workbook = new Spreadsheet();
            $dateValue = static fn ($value) => $value instanceof \DateTimeInterface
                ? $value->format('Y-m-d')
                : ($value ? substr((string) $value, 0, 10) : null);
            $sheets = [
                'Import Data' => [['relation_no', 'donor_code', 'donor_name', 'donor_type', 'lead_applicant', 'donor_country', 'donor_contact_person', 'donor_email', 'donor_phone', 'grant_no', 'agreement_no', 'agreement_date', 'grant_name', 'grant_value', 'currency_code', 'grant_status', 'grant_start_date', 'grant_end_date', 'reporting_period', 'donor_fiscal_calendar', 'terms_conditions', 'programme_code', 'programme_name', 'project_code', 'project_name', 'project_theme', 'project_manager_pic', 'project_start_date', 'project_end_date', 'project_budget', 'project_status']],
                'A Donor' => [['relation_no', 'donor_code', 'donor_name', 'donor_type', 'lead_applicant', 'country', 'contact_person', 'email', 'phone', 'status']],
                'B Program Project' => [['relation_no', 'grant_no', 'grant_value', 'programme_code', 'programme_name', 'project_code', 'project_name', 'theme', 'manager_pic', 'project_start_date', 'project_end_date', 'project_budget', 'project_status']],
                'C Grant Agreement' => [['relation_no', 'grant_no', 'agreement_no', 'agreement_date', 'agreement_name', 'grant_start_date', 'grant_end_date', 'currency_code', 'funding_source', 'bank_account', 'reporting_period', 'donor_fiscal_calendar', 'terms_conditions', 'grant_status']],
                'D Framework Workplan' => [['relation_no', 'project_code', 'record_type', 'level', 'code_output', 'description_activity', 'indicator', 'baseline', 'target', 'start_date', 'end_date', 'workplan_status']],
                'E Project Budget' => [['relation_no', 'project_code', 'budget_line_code', 'item_description', 'budget_category_id', 'total_amount', 'currency_code', 'coa_code']],
                'F Donor Reporting' => [['relation_no', 'grant_no', 'report_type', 'period_label', 'due_date', 'status', 'reminder_days', 'notes']],
                'G Documents' => [['relation_no', 'grant_no', 'project_code', 'document_type', 'file_path', 'status']],
            ];
            $seenDonors = [];
            $seenGrants = [];
            foreach ($records as $donor) {
                foreach ($donor->grantAgreements as $grant) {
                    $projects = $grant->projects->isEmpty() ? collect([null]) : $grant->projects;
                    foreach ($projects as $project) {
                        $relationNo = $project?->relation_no ?? $grant->relation_no;
                        $sheets['Import Data'][] = [$relationNo, $donor->code, $donor->name, $donor->type, $donor->lead_applicant, $donor->country, $donor->contact_person, $donor->email, $donor->phone, $grant->grant_no, $grant->agreement_no, $grant->agreement_date, $grant->agreement_name, $grant->grant_value, $grant->currency?->code, $grant->status, $dateValue($grant->start_date), $dateValue($grant->end_date), $grant->reporting_period, $grant->donor_fiscal_calendar, $grant->terms_conditions, $project?->program?->code, $project?->program?->name, $project?->code, $project?->name, $project?->theme, $project?->manager_name, $dateValue($project?->start_date), $dateValue($project?->end_date), $project?->total_budget, $project?->status];
                        if (! isset($seenDonors[$donor->id])) {
                            $sheets['A Donor'][] = [$relationNo, $donor->code, $donor->name, $donor->type, $donor->lead_applicant, $donor->country, $donor->contact_person, $donor->email, $donor->phone, $donor->status];
                            $seenDonors[$donor->id] = true;
                        }
                        if ($project) {
                            $sheets['B Program Project'][] = [$relationNo, $grant->grant_no, $grant->grant_value, $project->program?->code, $project->program?->name, $project->code, $project->name, $project->theme, $project->manager_name, $dateValue($project->start_date), $dateValue($project->end_date), $project->total_budget, $project->status];
                            foreach ($project->logframes as $framework) {
                                $sheets['D Framework Workplan'][] = [$relationNo, $project->code, 'framework', $framework->level, $framework->code, $framework->description, $framework->indicator, $framework->baseline, $framework->target, null, null, null];
                            }
                            foreach ($project->workplans as $workplan) {
                                $sheets['D Framework Workplan'][] = [$relationNo, $project->code, 'workplan', 'activity', $workplan->output_code, $workplan->activity, null, $workplan->baseline, null, optional($workplan->start_date)->format('Y-m-d'), optional($workplan->end_date)->format('Y-m-d'), $workplan->status];
                            }
                            foreach ($project->budgetLines as $budget) {
                                $sheets['E Project Budget'][] = [$relationNo, $project->code, $budget->line_code, $budget->description, $budget->budget_category_id, $budget->total_amount, $grant->currency?->code, $budget->gl_account_id];
                            }
                        }
                        if (! isset($seenGrants[$grant->id])) {
                            $sheets['C Grant Agreement'][] = [$relationNo, $grant->grant_no, $grant->agreement_no, $grant->agreement_date, $grant->agreement_name, $grant->start_date?->format('Y-m-d'), $grant->end_date?->format('Y-m-d'), $grant->currency?->code, $grant->fundingSource?->name, $grant->bankAccount?->account_number, $grant->reporting_period, $grant->donor_fiscal_calendar, $grant->terms_conditions, $grant->status];
                            foreach ($grant->reportingDeadlines as $deadline) {
                                $sheets['F Donor Reporting'][] = [$relationNo, $grant->grant_no, $deadline->report_type, $deadline->period_label, optional($deadline->due_date)->format('Y-m-d'), $deadline->status, $deadline->reminder_days, $deadline->notes];
                            }
                            foreach ((array) $grant->supporting_documents as $type => $path) {
                                $sheets['G Documents'][] = [$relationNo, $grant->grant_no, $project?->code, $type, $path, $path ? 'uploaded' : 'not_uploaded'];
                            }
                            if ($grant->agreement_document_path) {
                                $sheets['G Documents'][] = [$relationNo, $grant->grant_no, $project?->code, 'agreement_document', $grant->agreement_document_path, 'uploaded'];
                            }
                            $seenGrants[$grant->id] = true;
                        }
                    }
                }
            }
            foreach ($sheets as $index => $sheetRows) {
                $sheet = $index === 'Import Data' ? $workbook->getActiveSheet() : $workbook->createSheet();
                $sheet->setTitle($index);
                $sheet->fromArray($sheetRows, null, 'A1');
                $lastColumn = $sheet->getHighestColumn();
                $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                $sheet->getStyle("A1:{$lastColumn}1")->getFill()->setFillType('solid')->getStartColor()->setARGB('FF0F766E');
                $sheet->getStyle("A1:{$lastColumn}".$sheet->getHighestRow())->getAlignment()->setVertical('center');
                $sheet->freezePane('A2');
                $sheet->setAutoFilter("A1:{$lastColumn}1");
                for ($columnIndex = 1; $columnIndex <= Coordinate::columnIndexFromString($lastColumn); $columnIndex++) {
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(true);
                }
            }
            $workbook->setActiveSheetIndex(0);
            return response()->streamDownload(function () use ($workbook) {
                (new Xlsx($workbook))->save('php://output');
            }, 'donor-grant-integrated-'.now()->format('Y-m-d').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
        }
        $rows = [[
            'Relation No', 'Donor Code', 'Donor Name', 'Donor Type', 'Lead Applicant / Intermediary', 'Donor Country', 'Donor Contact', 'Donor Email', 'Donor Phone',
            'Grant No', 'Agreement No', 'Agreement Date', 'Agreement Name', 'Grant Value', 'Currency', 'Grant Status', 'Grant Start', 'Grant End', 'Reporting Period', 'Donor Fiscal Calendar', 'Terms & Conditions',
            'Programme Code', 'Programme Name', 'Project Code', 'Project Name', 'Project Theme', 'Project Manager / PIC', 'Project Start', 'Project End', 'Project Budget',
            'Framework Count', 'Workplan Count', 'Budget Line Count', 'Reporting Deadline Count',
        ]];
        foreach ($records as $donor) {
            foreach ($donor->grantAgreements as $grant) {
                $projects = $grant->projects->isEmpty() ? [null] : $grant->projects;
                foreach ($projects as $project) {
                    $rows[] = [
                        $project?->relation_no ?? $grant->relation_no,
                        $donor->code, $donor->name, $donor->type, $donor->lead_applicant, $donor->country, $donor->contact_person, $donor->email, $donor->phone,
                        $grant->grant_no, $grant->agreement_no, $grant->agreement_date, $grant->agreement_name, $grant->grant_value,
                        $grant->currency?->code, $grant->status, optional($grant->start_date)->format('Y-m-d'), optional($grant->end_date)->format('Y-m-d'),
                        $grant->reporting_period, $grant->donor_fiscal_calendar, $grant->terms_conditions,
                        $project?->program?->code, $project?->program?->name, $project?->code, $project?->name, $project?->theme, $project?->manager_name,
                        optional($project?->start_date)->format('Y-m-d'), optional($project?->end_date)->format('Y-m-d'), $project?->total_budget,
                        $project?->logframes?->count() ?? 0, $project?->workplans?->count() ?? 0, $project?->budgetLines?->count() ?? 0, $grant->reportingDeadlines->count(),
                    ];
                }
            }
        }
        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) fputcsv($handle, $row);
            fclose($handle);
        }, 'donor-grant-integrated-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt,xlsx,xls|max:20480']);

        try {
            $workbook = IOFactory::load($request->file('file')->getRealPath());
            $sheet = $workbook->getActiveSheet()->toArray(null, true, true, true);
            $headers = array_map(fn ($value) => strtolower(trim((string) $value)), array_shift($sheet) ?: []);
            $records = [];

            $cleanNumber = static function ($val): float {
                if (is_numeric($val)) return (float) $val;
                if (empty($val)) return 0.0;
                $str = preg_replace('/[^0-9,.-]/', '', (string) $val);
                if (str_contains($str, ',') && str_contains($str, '.')) {
                    if (strrpos($str, '.') > strrpos($str, ',')) {
                        $str = str_replace(',', '', $str);
                    } else {
                        $str = str_replace('.', '', $str);
                        $str = str_replace(',', '.', $str);
                    }
                } elseif (str_contains($str, ',')) {
                    $str = str_replace(',', '.', $str);
                }
                return is_numeric($str) ? (float) $str : 0.0;
            };

            $cleanDate = static function ($val, $default = null): ?string {
                if (empty($val)) return $default;
                $val = trim((string) $val);
                if (in_array(strtolower($val), ['', 'null', 'none', '-', 'contoh', 'wajib'], true)) return $default;
                try {
                    return \Illuminate\Support\Carbon::parse($val)->toDateString();
                } catch (\Throwable $e) {
                    return $default;
                }
            };

            $decodeRows = static function ($value): array {
                if (is_array($value)) return $value;
                $value = trim((string) $value);
                if ($value === '') return [];
                $decoded = json_decode($value, true);
                return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
            };

            $indexedRows = static function (array $values, string $prefix, array $fields, int $max = 10) use ($cleanDate): array {
                $rows = [];
                for ($index = 1; $index <= $max; $index++) {
                    $row = [];
                    $hasValue = false;
                    foreach ($fields as $field => $aliases) {
                        foreach ((array) $aliases as $alias) {
                            $key = $prefix.$index.'_'.$alias;
                            if (array_key_exists($key, $values) && trim((string) $values[$key]) !== '') {
                                $row[$field] = in_array($field, ['start_date', 'end_date', 'due_date'], true)
                                    ? $cleanDate($values[$key])
                                    : trim((string) $values[$key]);
                                $hasValue = true;
                                break;
                            }
                        }
                    }
                    if ($hasValue) $rows[] = $row;
                }
                return $rows;
            };

            $ignoredDonorCodes = [
                'donor_code', 'field', 'contoh', 'urutan', 'tambah project', 'tambah grant',
                'tambah donor', 'catatan', 'document type', 'no', 'keterangan', 'wajib'
            ];

            foreach ($sheet as $rowIndex => $row) {
                $values = array_combine($headers, array_values($row));
                if (!$values) continue;

                $donorCode = trim((string) ($values['donor_code'] ?? ''));
                $grantNo = trim((string) ($values['grant_no'] ?? ''));
                $projectCode = trim((string) ($values['project_code'] ?? ''));

                if ($donorCode === '' || $grantNo === '' || $projectCode === '') continue;
                if (in_array(strtolower($donorCode), $ignoredDonorCodes, true)) continue;
                if (in_array(strtolower($grantNo), ['grant_no', 'contoh', 'wajib'], true)) continue;
                if (in_array(strtolower($projectCode), ['project_code', 'contoh', 'wajib'], true)) continue;
                if (str_starts_with($donorCode, '#') || str_starts_with($donorCode, '//')) continue;

                $currencyValue = trim((string) ($values['currency_code'] ?? $values['currency_id'] ?? ''));
                $currencyCodeUpper = strtoupper($currencyValue ?: 'IDR');
                $currencyId = is_numeric($currencyValue)
                    ? (int) $currencyValue
                    : DB::table('currencies')->where('code', $currencyCodeUpper)->value('id');

                if (!$currencyId && $currencyCodeUpper !== '') {
                    $currencyId = DB::table('currencies')->insertGetId([
                        'code' => $currencyCodeUpper,
                        'name' => $currencyCodeUpper,
                        'symbol' => $currencyCodeUpper === 'GBP' ? '£' : ($currencyCodeUpper === 'USD' ? '$' : ($currencyCodeUpper === 'EUR' ? '€' : $currencyCodeUpper)),
                        'is_base_currency' => false,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                if (!$currencyId) {
                    $currencyId = DB::table('currencies')->value('id');
                }

                $grantStartDate = $cleanDate($values['grant_start_date'] ?? null, now()->toDateString());
                $grantEndDate = $cleanDate($values['grant_end_date'] ?? null, now()->addYear()->toDateString());
                $agreementDate = $cleanDate($values['agreement_date'] ?? null, null);
                $projectStartDate = $cleanDate($values['project_start_date'] ?? null, $grantStartDate);
                $projectEndDate = $cleanDate($values['project_end_date'] ?? null, $grantEndDate);

                $records[] = [
                    'relation_no' => !empty($values['relation_no']) ? trim((string) $values['relation_no']) : null,
                    'donor' => [
                        'code' => $donorCode,
                        'name' => !empty($values['donor_name']) ? trim((string) $values['donor_name']) : $donorCode,
                        'type' => !empty($values['donor_type']) ? trim((string) $values['donor_type']) : 'Other',
                        'lead_applicant' => !empty($values['lead_applicant']) ? trim((string) $values['lead_applicant']) : (!empty($values['lead_applicant_intermediary']) ? trim((string) $values['lead_applicant_intermediary']) : null),
                        'country' => !empty($values['donor_country']) ? trim((string) $values['donor_country']) : null,
                        'contact_person' => !empty($values['donor_contact_person']) ? trim((string) $values['donor_contact_person']) : null,
                        'email' => !empty($values['donor_email']) ? trim((string) $values['donor_email']) : null,
                        'phone' => !empty($values['donor_phone']) ? trim((string) $values['donor_phone']) : null,
                    ],
                    'grant' => [
                        'grant_no' => $grantNo,
                        'agreement_no' => !empty($values['agreement_no']) ? trim((string) $values['agreement_no']) : null,
                        'agreement_date' => $agreementDate,
                        'agreement_name' => !empty($values['grant_name']) ? trim((string) $values['grant_name']) : $grantNo,
                        'terms_conditions' => !empty($values['terms_conditions']) ? trim((string) $values['terms_conditions']) : null,
                        'reporting_period' => !empty($values['reporting_period']) ? trim((string) $values['reporting_period']) : null,
                        'donor_fiscal_calendar' => !empty($values['donor_fiscal_calendar']) ? trim((string) $values['donor_fiscal_calendar']) : 'JAN_DEC',
                        'grant_value' => $cleanNumber($values['grant_value'] ?? 0),
                        'currency_id' => $currencyId,
                        'status' => !empty($values['grant_status']) ? trim((string) $values['grant_status']) : 'draft',
                        'start_date' => $grantStartDate,
                        'end_date' => $grantEndDate,
                    ],
                    'programme' => [
                        'code' => !empty($values['programme_code']) ? trim((string) $values['programme_code']) : null,
                        'name' => !empty($values['programme_name']) ? trim((string) $values['programme_name']) : null,
                    ],
                    'project' => [
                        'code' => $projectCode,
                        'name' => !empty($values['project_name']) ? trim((string) $values['project_name']) : $projectCode,
                        'theme' => !empty($values['project_theme']) ? trim((string) $values['project_theme']) : null,
                        'manager_name' => !empty($values['project_manager_pic']) ? trim((string) $values['project_manager_pic']) : null,
                        'start_date' => $projectStartDate,
                        'end_date' => $projectEndDate,
                        'total_budget' => $cleanNumber($values['project_budget'] ?? 0),
                        'status' => !empty($values['project_status']) ? trim((string) $values['project_status']) : 'draft',
                    ],
                    'frameworks' => $decodeRows($values['framework_json'] ?? $values['framework_workplan_json'] ?? null),
                    'workplans' => $indexedRows($values, 'workplan_', [
                        'activity_code' => ['activity_code', 'code'], 'activity' => ['activity'], 'output_code' => ['output_code'],
                        'start_date' => ['start_date'], 'end_date' => ['end_date'], 'status' => ['status'], 'baseline' => ['baseline'], 'notes' => ['notes'],
                    ]),
                    'deadlines' => $indexedRows($values, 'reporting_', [
                        'report_type' => ['report_type', 'type'], 'period_label' => ['period_label', 'period'], 'due_date' => ['due_date'],
                        'status' => ['status'], 'reminder_days' => ['reminder_days'], 'notes' => ['notes'],
                    ]),
                ];
            }

            if (!$records) {
                return response()->json(['message' => 'Template CSV/Excel Donor & Grant Terintegrasi tidak valid atau tidak memiliki baris data.'], 422);
            }

            $created = DB::transaction(function () use ($records) {
                $count = 0;
                foreach ($records as $record) {
                    // Include soft-deleted donors when resolving by unique code;
                    // otherwise a re-import attempts to insert the same code and
                    // fails on donors_code_unique.
                    $donor = Donor::withoutGlobalScope('fiscal_year_context')->withTrashed()->firstOrNew(['code' => $record['donor']['code']]);
                    $donor->fill(array_merge($record['donor'], ['is_active' => true, 'status' => 'active']));
                    if ($donor->trashed()) $donor->restore();
                    $donor->save();
                    $existingGrant = GrantAgreement::withoutGlobalScope('fiscal_year_context')
                        ->where('donor_id', $donor->id)
                        ->where('grant_no', $record['grant']['grant_no'])
                        ->first();
                    $relationNo = $this->resolveRelationNo($record, $existingGrant);
                    $grant = GrantAgreement::withoutGlobalScope('fiscal_year_context')->updateOrCreate(
                        ['donor_id' => $donor->id, 'grant_no' => $record['grant']['grant_no']],
                        array_merge($record['grant'], ['relation_no' => $relationNo, 'is_active' => true])
                    );
                    $programId = null;
                    if (! empty($record['programme']['code'])) {
                        $programId = Program::updateOrCreate(
                            ['code' => $record['programme']['code']],
                            ['name' => $record['programme']['name'] ?: $record['programme']['code'], 'is_active' => true]
                        )->id;
                    }
                    $project = Project::withoutGlobalScope('fiscal_year_context')->updateOrCreate(
                        ['grant_agreement_id' => $grant->id, 'code' => $record['project']['code']],
                        array_merge($record['project'], ['program_id' => $programId, 'relation_no' => $relationNo, 'budget_currency_id' => $grant->currency_id, 'is_active' => true])
                    );
                    $this->syncProjectFiscalYears($project, $grant);
                    $this->syncFrameworksAndWorkplans(
                        $project,
                        $grant,
                        $donor,
                        $relationNo,
                        $record['frameworks'] ?? [],
                        $record['workplans'] ?? [],
                        $grant->fiscal_year_id
                    );
                    foreach ($record['deadlines'] ?? [] as $deadline) {
                        $reportType = trim((string) ($deadline['report_type'] ?? ''));
                        $dueDate = $deadline['due_date'] ?? null;
                        if ($reportType === '' || empty($dueDate)) continue;
                        GrantReportingDeadline::updateOrCreate(
                            ['grant_agreement_id' => $grant->id, 'report_type' => $reportType, 'period_label' => ($deadline['period_label'] ?? null)],
                            ['due_date' => $dueDate, 'status' => $deadline['status'] ?? 'upcoming', 'notes' => $deadline['notes'] ?? null, 'reminder_days' => (int) ($deadline['reminder_days'] ?? 7)]
                        );
                    }
                    $count++;
                }
                return $count;
            });

            // Process optional Framework & Workplan sheet after the core
            // Donor/Grant/Project rows have been created.
            $frameworkSheet = $workbook->getSheetByName('D Framework Workplan');
            if ($frameworkSheet) {
                $frameworkRows = $frameworkSheet->toArray(null, true, true, true);
                $frameworkHeaders = array_map(fn ($value) => strtolower(trim((string) $value)), array_shift($frameworkRows) ?: []);
                foreach ($frameworkRows as $row) {
                    $values = array_combine($frameworkHeaders, array_values($row));
                    $relation = trim((string) ($values['relation_no'] ?? ''));
                    $recordType = strtolower(trim((string) ($values['record_type'] ?? 'framework')));
                    $code = trim((string) ($values['code'] ?? $values['code_output'] ?? ''));
                    $description = trim((string) ($values['description'] ?? $values['description_activity'] ?? $code));
                    if ($relation === '' || $code === '' || strtolower($relation) === 'relation_no') continue;
                    $project = Project::withoutGlobalScope('fiscal_year_context')->where('relation_no', $relation)->first();
                    if (! $project) continue;
                    $logframe = ProjectLogframe::updateOrCreate(
                        ['project_id' => $project->id, 'code' => $code],
                        ['relation_no' => $relation, 'fiscal_year_id' => $request->input('fiscal_year_id'), 'level' => $values['level'] ?? 'output', 'description' => $description, 'indicator' => $values['indicator'] ?? null, 'baseline' => $values['baseline'] ?? null, 'target' => $values['target'] ?? null, 'period_start' => $values['start_date'] ?? null, 'period_end' => $values['end_date'] ?? null, 'is_active' => true]
                    );

                    if ($recordType === 'workplan') {
                        ProjectWorkplan::updateOrCreate(
                            ['project_id' => $project->id, 'activity_code' => $code],
                            [
                                'relation_no' => $relation,
                                'logframe_id' => $logframe->id,
                                'grant_agreement_id' => $project->grant_agreement_id,
                                'donor_id' => $project->grantAgreement?->donor_id,
                                'fiscal_year_id' => $request->input('fiscal_year_id'),
                                'output_code' => $values['output_code'] ?? null,
                                'activity' => $description,
                                'start_date' => $values['start_date'] ?? null,
                                'end_date' => $values['end_date'] ?? null,
                                'status' => $values['workplan_status'] ?? $values['status'] ?? 'planned',
                                'baseline' => $values['baseline'] ?? null,
                                'notes' => $values['notes'] ?? null,
                                'is_active' => true,
                            ]
                        );
                    }
                }
            }
            // Process optional Donor Reporting sheet using the same fields as
            // the integrated form validation rules.
            $reportingSheet = $workbook->getSheetByName('F Donor Reporting');
            if ($reportingSheet) {
                $reportingRows = $reportingSheet->toArray(null, true, true, true);
                $reportingHeaders = array_map(fn ($value) => strtolower(trim((string) $value)), array_shift($reportingRows) ?: []);
                foreach ($reportingRows as $row) {
                    $values = array_combine($reportingHeaders, array_values($row));
                    $relation = trim((string) ($values['relation_no'] ?? ''));
                    $reportType = trim((string) ($values['report_type'] ?? ''));
                    $dueDate = trim((string) ($values['due_date'] ?? ''));
                    if ($relation === '' || $reportType === '' || $dueDate === '' || strtolower($relation) === 'relation_no') continue;
                    $grant = GrantAgreement::withoutGlobalScope('fiscal_year_context')->where('relation_no', $relation)->first();
                    if (! $grant) continue;
                    GrantReportingDeadline::updateOrCreate(
                        ['grant_agreement_id' => $grant->id, 'report_type' => $reportType, 'period_label' => ($values['period_label'] ?? null)],
                        ['due_date' => $dueDate, 'status' => $values['status'] ?? 'upcoming', 'notes' => $values['notes'] ?? null, 'reminder_days' => (int) ($values['reminder_days'] ?? 7)]
                    );
                }
            }

            // Budget lines are part of the integrated workbook contract. The
            // main sheet creates the donor/grant/project keys first; this
            // sheet then resolves those keys and persists the budget rows.
            $budgetSheet = $workbook->getSheetByName('E Project Budget');
            if ($budgetSheet) {
                $budgetRows = $budgetSheet->toArray(null, true, true, true);
                $budgetHeaders = array_map(fn ($value) => strtolower(trim((string) $value)), array_shift($budgetRows) ?: []);
                foreach ($budgetRows as $row) {
                    $values = array_combine($budgetHeaders, array_values($row));
                    if (! is_array($values)) continue;

                    $relation = trim((string) ($values['relation_no'] ?? ''));
                    $projectCode = trim((string) ($values['project_code'] ?? ''));
                    $lineCode = trim((string) ($values['budget_line_code'] ?? $values['line_code'] ?? ''));
                    if ($relation === '' || $lineCode === '' || strtolower($relation) === 'relation_no') continue;

                    $project = Project::withoutGlobalScope('fiscal_year_context')
                        ->where(function ($query) use ($relation, $projectCode) {
                            $query->where('relation_no', $relation);
                            if ($projectCode !== '') $query->orWhere('code', $projectCode);
                        })->first();
                    if (! $project) continue;

                    $coaCode = trim((string) ($values['coa_code'] ?? ''));
                    $coaId = $coaCode !== ''
                        ? ChartOfAccount::withoutGlobalScope('fiscal_year_context')->where('code', $coaCode)->value('id')
                        : null;
                    $categoryValue = trim((string) ($values['budget_category_id'] ?? ''));
                    $categoryId = $categoryValue !== '' && is_numeric($categoryValue)
                        ? (int) $categoryValue
                        : ($categoryValue !== '' ? BudgetCategory::query()->where('code', $categoryValue)->orWhere('name', $categoryValue)->value('id') : null);

                    BudgetLine::withoutGlobalScope('fiscal_year_context')->updateOrCreate(
                        ['project_id' => $project->id, 'line_code' => $lineCode],
                        [
                            'grant_agreement_id' => $project->grant_agreement_id,
                            'relation_no' => $project->relation_no ?: $relation,
                            'description' => trim((string) ($values['item_description'] ?? $lineCode)),
                            'budget_category_id' => $categoryId,
                            'total_amount' => $cleanNumber($values['total_amount'] ?? 0),
                            'currency_id' => $project->budget_currency_id,
                            'gl_account_id' => $coaId,
                            'is_active' => true,
                        ]
                    );
                }
            }
            return response()->json(['success' => true, 'message' => "Import Donor & Grant Terintegrasi berhasil. {$created} baris diproses.", 'created' => $created]);
        } catch (\Throwable $exception) {
            Log::error('Import integrated donor grant failed: ' . $exception->getMessage(), [
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses import data: ' . $exception->getMessage(),
            ], 422);
        }
    }

    public function template()
    {
        $headers = ['relation_no', 'donor_code', 'donor_name', 'donor_type', 'lead_applicant', 'donor_country', 'donor_contact_person', 'donor_email', 'donor_phone', 'grant_no', 'agreement_no', 'agreement_date', 'grant_name', 'grant_value', 'currency_code', 'grant_status', 'grant_start_date', 'grant_end_date', 'reporting_period', 'donor_fiscal_calendar', 'terms_conditions', 'programme_code', 'programme_name', 'project_code', 'project_name', 'project_theme', 'project_manager_pic', 'project_start_date', 'project_end_date', 'project_budget', 'project_status', 'Import Note'];
        $year = now()->year;
        $rows = [
            ["KT-REL-D1-G1-P1", 'DONOR-A', 'Ford Foundation', 'Foundation', 'Environmental Funders Network', 'United States', 'Jane Smith', 'jane.smith@example.org', '+1 202 555 0100', 'GRANT-A1', 'AGR-FF-'.$year.'-001', "{$year}-01-01", 'EUDR Traceability Programme', 1000000000, 'IDR', 'active', "{$year}-01-01", "{$year}-12-31", 'Quarterly', 'JAN_DEC', 'Financial and narrative reports required every quarter.', 'PROG-EUDR', 'Sustainable Commodities', 'PROJECT-A1', 'Traceability EUDR', 'Forest Governance', 'Denny Bhatara', "{$year}-01-01", "{$year}-12-31", 600000000, 'active', 'CONTOH DATA UTAMA: donor baru + grant baru + project pertama.'],
            ["KT-REL-D1-G1-P2", 'DONOR-A', 'Ford Foundation', 'Foundation', 'Environmental Funders Network', 'United States', 'Jane Smith', 'jane.smith@example.org', '+1 202 555 0100', 'GRANT-A1', 'AGR-FF-'.$year.'-001', "{$year}-01-01", 'EUDR Traceability Programme', 1000000000, 'IDR', 'active', "{$year}-01-01", "{$year}-12-31", 'Quarterly', 'JAN_DEC', 'Financial and narrative reports required every quarter.', 'PROG-EUDR', 'Sustainable Commodities', 'PROJECT-A2', 'Smallholder Support', 'Community Capacity', 'Rika Putri', "{$year}-04-01", "{$year}-12-31", 400000000, 'active', 'CONTOH TAMBAH PROJECT: donor dan grant sama, project_code baru.'],
        ];
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Input Data');
        $sheet->fromArray([$headers, ...$rows], null, 'A1');
        $lastColumn = $sheet->getHighestColumn();
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()->setFillType('solid')->getStartColor()->setARGB('FF1F4E78');
        foreach ([2 => 'FFDCE6F1', 3 => 'FFE2F0D9', 4 => 'FFFFF2CC', 5 => 'FFDCE6F1'] as $row => $color) {
            $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->getFill()->setFillType('solid')->getStartColor()->setARGB($color);
        }
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}1");
        for ($columnIndex = 1; $columnIndex <= Coordinate::columnIndexFromString($lastColumn); $columnIndex++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(true);
        }
        $sheet->getColumnDimension($lastColumn)->setWidth(78);
        $sheet->getStyle("{$lastColumn}1:{$lastColumn}5")->getAlignment()->setWrapText(true);
        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('Instructions');
        $instructions->fromArray([
            ['Urutan', 'Donor → Grant → Project.'],
            ['Tambah project', 'Ulangi Donor Code dan Grant No yang sama, lalu gunakan Project Code baru.'],
            ['Tambah grant', 'Ulangi Donor Code yang sama, lalu gunakan Grant No dan Project Code baru.'],
            ['Tambah donor', 'Gunakan Donor Code, Grant No, dan Project Code baru.'],
            ['Catatan', 'Semua baris berwarna adalah dummy. Hapus sebelum import data asli.'],
        ], null, 'A1');
        $instructions->getColumnDimension('A')->setWidth(22);
        $instructions->getColumnDimension('B')->setWidth(100);
        $instructions->getStyle('A1:A5')->getFont()->setBold(true);

        $documentSheets = [
            'A Donor' => [
                ['Field', 'Contoh', 'Wajib', 'Keterangan'],
                ['donor_code', 'DONOR-A', 'Ya', 'Kode donor unik'], ['donor_name', 'Ford Foundation', 'Ya', 'Nama funder'],
                ['donor_type', 'Foundation', 'Ya', 'Tipe donor'], ['lead_applicant', 'Environmental Funders Network', 'Tidak', 'Lead applicant/intermediary'],
                ['donor_country', 'United States', 'Tidak', 'Negara'], ['donor_contact_person', 'Jane Smith', 'Tidak', 'Kontak/PIC'],
                ['donor_email', 'jane.smith@example.org', 'Tidak', 'Email'], ['donor_phone', '+1 202 555 0100', 'Tidak', 'Telepon'],
            ],
            'B Program Project' => [
                ['Field', 'Contoh', 'Wajib', 'Keterangan'],
                ['programme_code', 'PROG-EUDR', 'Tidak', 'Kode programme'], ['programme_name', 'Sustainable Commodities', 'Tidak', 'Nama programme'],
                ['project_code', 'PROJECT-A1', 'Ya', 'Kode project unik di dalam grant'], ['project_name', 'Traceability EUDR', 'Ya', 'Nama project'],
                ['project_theme', 'Forest Governance', 'Tidak', 'Tema project'], ['project_manager_pic', 'Denny Bhatara', 'Tidak', 'Project Manager/PIC'],
                ['project_start_date', "{$year}-01-01", 'Tidak', 'Format YYYY-MM-DD'], ['project_end_date', "{$year}-12-31", 'Tidak', 'Format YYYY-MM-DD'],
                ['project_budget', 600000000, 'Ya', 'Angka tanpa simbol mata uang'], ['project_status', 'active', 'Tidak', 'draft/active/closed'],
            ],
            'C Grant Agreement' => [
                ['Field', 'Contoh', 'Wajib', 'Keterangan'],
                ['grant_no', 'GRANT-A1', 'Ya', 'Nomor grant'], ['agreement_no', 'AGR-FF-'.$year.'-001', 'Tidak', 'Nomor agreement'],
                ['agreement_date', "{$year}-01-01", 'Tidak', 'Tanggal agreement'], ['grant_name', 'EUDR Traceability Programme', 'Ya', 'Nama agreement'],
                ['grant_value', 1000000000, 'Ya', 'Nilai hibah'], ['currency_code', 'IDR', 'Ya', 'Kode mata uang'],
                ['grant_start_date', "{$year}-01-01", 'Ya', 'Tanggal mulai'], ['grant_end_date', "{$year}-12-31", 'Ya', 'Tanggal selesai'],
                ['reporting_period', 'Quarterly', 'Tidak', 'Periode pelaporan'], ['donor_fiscal_calendar', 'JAN_DEC', 'Tidak', 'JAN_DEC/APR_MAR'],
                ['terms_conditions', 'Quarterly financial and narrative reports.', 'Tidak', 'Syarat dan ketentuan'],
            ],
            'D Framework Workplan' => [
                ['Field', 'Contoh', 'Wajib', 'Keterangan'], ['relation_no', 'KT-REL-D1-G1-P1', 'Ya', 'Relasi project'],
                ['level', 'output', 'Ya', 'impact/outcome/output/activity'], ['code', 'OUT-1', 'Ya', 'Kode logframe'],
                ['description', 'Traceability system adopted', 'Ya', 'Deskripsi output/activity'], ['indicator', '5 organizations using system', 'Tidak', 'Indikator'],
                ['baseline', '0 organizations', 'Tidak', 'Nilai awal'], ['target', '5 organizations', 'Tidak', 'Target'],
                ['start_date', "{$year}-02-01", 'Tidak', 'Mulai activity'], ['end_date', "{$year}-03-31", 'Tidak', 'Selesai activity'],
            ],
            'E Project Budget' => [
                ['Field', 'Contoh', 'Wajib', 'Keterangan'], ['relation_no', 'KT-REL-D1-G1-P1', 'Ya', 'Relasi project'],
                ['line_code', 'BL-001', 'Ya', 'Kode budget line'], ['description', 'Workshop and training', 'Ya', 'Uraian item belanja'],
                ['budget_category_code', 'BCAT-100', 'Ya', 'Kategori biaya'], ['quantity', 2, 'Tidak', 'Jumlah'],
                ['unit_price', 75000000, 'Tidak', 'Harga satuan'], ['total_amount', 150000000, 'Ya', 'Total budget'], ['coa_code', '5100', 'Tidak', 'COA biaya'],
            ],
            'F Donor Reporting' => [
                ['Field', 'Contoh', 'Wajib', 'Keterangan'], ['relation_no', 'KT-REL-D1-G1-P1', 'Ya', 'Relasi grant/project'],
                ['report_type', 'Financial Report', 'Ya', 'Tipe laporan'], ['period_label', 'Q1 '.$year, 'Ya', 'Label periode'],
                ['due_date', "{$year}-04-15", 'Ya', 'Jatuh tempo'], ['status', 'upcoming', 'Tidak', 'Status laporan'],
                ['reminder_days', 14, 'Tidak', 'Hari pengingat'], ['notes', 'Quarterly financial report', 'Tidak', 'Catatan'],
            ],
            'G Documents' => [
                ['Document Type', 'Contoh Nama File', 'Wajib', 'Keterangan'],
                ['Proposal', 'GRANT-A1_Proposal.pdf', 'Ya', 'Upload melalui menu Others/Documents'],
                ['Signed Agreement', 'GRANT-A1_Signed_Agreement.pdf', 'Ya', 'Dokumen bertanda tangan'],
                ['Workplan', 'PROJECT-A1_Workplan.xlsx', 'Ya', 'Versi final'],
                ['Detailed Budget', 'PROJECT-A1_Detailed_Budget.xlsx', 'Ya', 'Sesuai budget line'],
                ['Amendment', 'GRANT-A1_Amendment_01.pdf', 'Tidak', 'Jika ada perubahan kontrak'],
            ],
        ];
        foreach ($documentSheets as $title => $rowsForSheet) {
            $documentSheet = $spreadsheet->createSheet();
            $documentSheet->setTitle($title);
            $documentSheet->fromArray($rowsForSheet, null, 'A1');
            $documentSheet->getStyle('A1:D1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $documentSheet->getStyle('A1:D1')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF0F766E');
            $documentSheet->getStyle('A1:D'.count($rowsForSheet))->getAlignment()->setWrapText(true)->setVertical('center');
            $documentSheet->freezePane('A2');
            $documentSheet->getColumnDimension('A')->setWidth(28);
            $documentSheet->getColumnDimension('B')->setWidth(42);
            $documentSheet->getColumnDimension('C')->setWidth(14);
            $documentSheet->getColumnDimension('D')->setWidth(65);
        }
        $spreadsheet->setActiveSheetIndex(0);
        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'donor-grant-integrated-template.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function syncProjectFiscalYears(Project $project, GrantAgreement $grant, ?Request $request = null): ?int
    {
        $explicitId = $request?->input('fiscal_year_id')
            ?: $request?->query('fiscal_year_id')
            ?: $request?->header('X-Fiscal-Year-Id')
            ?: $grant->fiscal_year_id;

        $startDate = $project->start_date ?: $grant->start_date;
        $endDate = $project->end_date ?: $grant->end_date;

        $overlapIds = collect();
        if ($startDate && $endDate) {
            $overlapIds = FiscalYear::query()
                ->whereDate('start_date', '<=', $endDate)
                ->whereDate('end_date', '>=', $startDate)
                ->pluck('id');
        }

        $ids = collect([$explicitId ? (int) $explicitId : null])
            ->filter()
            ->merge($overlapIds)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            $activeId = FiscalYear::query()->where('is_active', true)->orderByDesc('year')->value('id');
            if ($activeId) {
                $ids = collect([(int) $activeId]);
            }
        }

        if ($ids->isNotEmpty()) {
            $project->fiscalYears()->syncWithoutDetaching($ids->all());
        }

        return $explicitId ? (int) $explicitId : ($ids->first() ? (int) $ids->first() : null);
    }

    private function mergeMultipartPayload(Request $request)
    {
        if (! $request->filled('payload')) {
            return null;
        }
        $payload = json_decode((string) $request->input('payload'), true);
        if (! is_array($payload)) {
            return response()->json(['message' => 'Payload integrated record tidak valid.'], 422);
        }
        $request->merge($payload);

        return null;
    }

    private function storeUploadedDocuments(Request $request, ?GrantAgreement $grant = null): array
    {
        $documents = is_array($grant?->supporting_documents) ? $grant->supporting_documents : [];
        foreach (['proposal', 'signed_agreement', 'workplan', 'detailed_budget', 'amendment'] as $documentKey) {
            $file = $request->file("documents.{$documentKey}");
            if ($file) {
                if (! $file->isValid()) {
                    throw new \RuntimeException("Upload dokumen {$documentKey} gagal: ".$file->getErrorMessage());
                }
                $documents[$documentKey] = $file->store('donor-grant-documents', 'public');
            }
        }
        $agreementFile = $request->file('documents.agreement_document');
        if ($agreementFile && ! $agreementFile->isValid()) {
            throw new \RuntimeException('Upload Dokumen Agreement gagal: '.$agreementFile->getErrorMessage());
        }
        $agreementPath = $agreementFile
            ? $agreementFile->store('donor-grant-documents', 'public')
            : $grant?->agreement_document_path;

        return [$documents, $agreementPath];
    }

    private function syncFrameworksAndWorkplans(
        Project $project,
        GrantAgreement $grant,
        Donor $donor,
        string $relationNo,
        array $frameworks,
        array $workplans,
        ?int $fiscalYearId = null
    ): void {
        $existingLogframes = ProjectLogframe::withoutGlobalScope('fiscal_year_context')
            ->withTrashed()
            ->where('project_id', $project->id)
            ->get()
            ->keyBy(fn (ProjectLogframe $item) => strtoupper(trim((string) $item->code)));

        $logframeByCode = [];
        $keptLogframeIds = [];
        $fallbackLogframeId = null;

        foreach (collect($frameworks)->unique(fn ($item) => strtoupper(trim((string) ($item['code'] ?? ''))))->values() as $framework) {
            $codeKey = strtoupper(trim((string) ($framework['code'] ?? '')));
            $existing = $codeKey !== '' ? $existingLogframes->get($codeKey) : null;
            $attributes = array_merge($framework, [
                'project_id' => $project->id,
                'relation_no' => $relationNo,
                'fiscal_year_id' => $framework['fiscal_year_id'] ?? ($existing?->fiscal_year_id ?? $fiscalYearId),
            ]);

            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }
                $existing->update($attributes);
                $savedLogframe = $existing->fresh();
            } else {
                $savedLogframe = ProjectLogframe::create($attributes);
            }

            $keptLogframeIds[] = $savedLogframe->id;
            if ($codeKey !== '') {
                $logframeByCode[$codeKey] = $savedLogframe->id;
            }
            if ($fallbackLogframeId === null && in_array($savedLogframe->level, ['output', 'activity'], true)) {
                $fallbackLogframeId = $savedLogframe->id;
            }
        }

        ProjectLogframe::withoutGlobalScope('fiscal_year_context')
            ->where('project_id', $project->id)
            ->whereNotIn('id', $keptLogframeIds)
            ->get()
            ->each->delete();

        $existingWorkplans = ProjectWorkplan::withoutGlobalScope('fiscal_year_context')
            ->where('project_id', $project->id)
            ->get();
        $keptWorkplanIds = [];

        foreach ($workplans as $workplan) {
            $outputKey = strtoupper(trim((string) ($workplan['output_code'] ?? '')));
            $activityKey = strtolower(trim((string) ($workplan['activity'] ?? '')));
            $existingWorkplan = ! empty($workplan['id'])
                ? $existingWorkplans->firstWhere('id', (int) $workplan['id'])
                : $existingWorkplans->first(function (ProjectWorkplan $item) use ($outputKey, $activityKey, $keptWorkplanIds) {
                    return ! in_array($item->id, $keptWorkplanIds, true)
                        && strtoupper(trim((string) $item->output_code)) === $outputKey
                        && strtolower(trim((string) $item->activity)) === $activityKey;
                });

            $attributes = array_merge($workplan, [
                'project_id' => $project->id,
                'relation_no' => $relationNo,
                'grant_agreement_id' => $grant->id,
                'donor_id' => $donor->id,
                'fiscal_year_id' => $workplan['fiscal_year_id'] ?? ($existingWorkplan?->fiscal_year_id ?? $fiscalYearId),
                'logframe_id' => $workplan['logframe_id'] ?? ($logframeByCode[$outputKey] ?? $fallbackLogframeId),
            ]);

            if ($existingWorkplan) {
                $existingWorkplan->update($attributes);
                $keptWorkplanIds[] = $existingWorkplan->id;
            } else {
                $createdWorkplan = ProjectWorkplan::create($attributes);
                $keptWorkplanIds[] = $createdWorkplan->id;
            }
        }

        $existingWorkplans->whereNotIn('id', $keptWorkplanIds)->each->delete();
    }

    private function budgetLineDefaults(array $budget, GrantAgreement $grant, ?int $fiscalYearId = null): array
    {
        // Integrated Donor & Grant writes BudgetLine directly, so keep the same
        // automatic Cost Category -> expense CoA mapping as the normal endpoint.
        if (empty($budget['gl_account_id'])) {
            $mapping = ['BCAT-100' => '5100', 'BCAT-200' => '5200', 'BCAT-300' => '5300'];
            $categoryCode = BudgetCategory::query()->whereKey($budget['budget_category_id'] ?? null)->value('code');
            $coaCode = $mapping[$categoryCode] ?? null;
            if ($coaCode) {
                $budget['gl_account_id'] = ChartOfAccount::query()->where('code', $coaCode)->where('account_type', 'expense')->value('id');
                $budget['cost_type'] = $coaCode;
            }
        }

        return array_merge($budget, [
            'fiscal_year_id' => $budget['fiscal_year_id'] ?? $grant->fiscal_year_id ?? $fiscalYearId,
            'currency_id' => $budget['currency_id'] ?? $grant->currency_id,
            'proposal_period' => $budget['proposal_period'] ?? 'annual',
            'unit_price' => $budget['unit_price'] ?? $budget['total_amount'],
            'quantity' => $budget['quantity'] ?? 1,
            'period_allocations' => $budget['period_allocations'] ?? [],
            'is_active' => true,
        ]);
    }

    public function update(Request $request, int|string $donor)
    {
        $donor = $this->findDonorAcrossFiscalYears($donor);
        if ($invalid = $this->mergeMultipartPayload($request)) {
            return $invalid;
        }

        $targetGrantId = $request->input('grant.id');
        $targetGrantNo = $request->input('grant.grant_no');
        // Older integrated-form payloads put relation_no inside the grant
        // object. Normalize both shapes before resolving the record to edit.
        $reqRelationNo = $request->input('relation_no') ?: $request->input('grant.relation_no');

        $currentGrant = null;
        if ($targetGrantId) {
            $currentGrant = GrantAgreement::withoutGlobalScope('fiscal_year_context')->find($targetGrantId);
        }
        if (! $currentGrant && $targetGrantNo) {
            $currentGrant = $donor->grantAgreements()->withoutGlobalScope('fiscal_year_context')->where('grant_no', $targetGrantNo)->first();
        }
        if (! $currentGrant && $reqRelationNo) {
            $currentGrant = $donor->grantAgreements()->withoutGlobalScope('fiscal_year_context')->where('relation_no', $reqRelationNo)->first()
                ?: GrantAgreement::withoutGlobalScope('fiscal_year_context')->where('relation_no', $reqRelationNo)->first();
        }
        if (! $currentGrant) {
            $currentGrant = $donor->grantAgreements()->withoutGlobalScope('fiscal_year_context')->first();
        }

        // Some older edit payloads omit grant.id. Once the grant is resolved
        // from the donor/relation, inject its ID so both validation and the
        // update transaction unmistakably target the same record.
        if ($currentGrant && ! $targetGrantId) {
            $request->merge([
                'grant' => array_merge((array) $request->input('grant', []), ['id' => $currentGrant->id]),
            ]);
            $targetGrantId = $currentGrant->id;
        }

        $currentProject = $currentGrant?->projects()->withoutGlobalScope('fiscal_year_context')->first();

        $ignoreGrantId = $currentGrant?->id;
        if (! $ignoreGrantId && $reqRelationNo) {
            $ignoreGrantId = GrantAgreement::withoutGlobalScope('fiscal_year_context')->where('relation_no', $reqRelationNo)->value('id');
        }

        $data = $request->validate([
            'relation_no' => ['nullable', 'string', 'max:100', Rule::unique('grant_agreements', 'relation_no')->ignore($ignoreGrantId)],
            'donor' => 'required|array', 'donor.code' => 'required|string|max:30', 'donor.name' => 'required|string|max:150', 'donor.type' => 'required|string|max:50', 'donor.lead_applicant' => 'nullable|string|max:150', 'donor.country' => 'nullable|string|max:100', 'donor.contact_person' => 'nullable|string|max:100', 'donor.email' => 'nullable|email|max:150', 'donor.phone' => 'nullable|string|max:50',
            'grant' => 'required|array',
            'grant.id' => 'nullable|integer',
            'grant.grant_no' => ['required', 'string', 'max:50', Rule::unique('grant_agreements', 'grant_no')->ignore($ignoreGrantId)],
            'grant.agreement_no' => 'nullable|string|max:50', 'grant.agreement_date' => 'nullable|date', 'grant.agreement_name' => 'required|string|max:200', 'grant.terms_conditions' => 'nullable|string', 'grant.reporting_period' => 'nullable|string|max:30', 'grant.donor_fiscal_calendar' => 'nullable|in:JAN_DEC,APR_MAR', 'grant.currency_id' => 'required|exists:currencies,id', 'grant.funding_source_id' => 'nullable|exists:funding_sources,id', 'grant.bank_account_id' => 'nullable|exists:bank_accounts,id', 'grant.grant_value' => 'required|numeric|min:0', 'grant.start_date' => 'required|date', 'grant.end_date' => 'required|date|after_or_equal:grant.start_date', 'grant.status' => 'nullable|in:draft,active,closed,suspended',
            'project' => 'required|array',
            'project.id' => 'nullable|integer',
            'project.code' => 'required|string|max:30', 'project.name' => 'required|string|max:150', 'project.program_id' => 'nullable|exists:programs,id', 'project.theme' => 'nullable|string|max:200', 'project.manager_name' => 'nullable|string|max:100', 'project.start_date' => 'nullable|date', 'project.end_date' => 'nullable|date|after_or_equal:project.start_date', 'project.total_budget' => 'nullable|numeric|min:0', 'project.partner_ids' => 'nullable|array', 'project.partner_ids.*' => 'integer|exists:beneficiary_partners,id', 'project.funding_source_ids' => 'nullable|array', 'project.funding_source_ids.*' => 'integer|exists:funding_sources,id',
            'programme' => 'nullable|array', 'programme.id' => 'nullable|integer|exists:programs,id', 'programme.code' => 'nullable|string|max:50', 'programme.name' => 'nullable|string|max:150', 'programme.objective' => 'nullable|string',
            'frameworks' => 'array', 'frameworks.*.level' => 'required|in:impact,outcome,output,activity', 'frameworks.*.code' => 'required|string|max:50', 'frameworks.*.description' => 'required|string', 'frameworks.*.indicator' => 'nullable|string|max:255', 'frameworks.*.baseline' => 'nullable|string', 'frameworks.*.target' => 'nullable|string',
            'workplans' => 'array', 'workplans.*.activity' => 'required|string|max:255', 'workplans.*.output_code' => 'nullable|string|max:100', 'workplans.*.start_date' => 'nullable|date', 'workplans.*.end_date' => 'nullable|date', 'workplans.*.status' => 'nullable|string|max:50', 'workplans.*.baseline' => 'nullable|string',
            'budgets' => 'array', 'budgets.*.id' => 'nullable|integer', 'budgets.*.line_code' => 'required|string|max:50', 'budgets.*.description' => 'required|string|max:255', 'budgets.*.budget_category_id' => 'required|exists:budget_categories,id', 'budgets.*.total_amount' => 'required|numeric|min:0',
            'deadlines' => 'array', 'deadlines.*.report_type' => 'required|string|max:100', 'deadlines.*.period_label' => 'nullable|string|max:100', 'deadlines.*.due_date' => 'required|date', 'deadlines.*.status' => 'nullable|string|max:50', 'deadlines.*.notes' => 'nullable|string', 'deadlines.*.reminder_days' => 'nullable|integer',
            'documents' => 'nullable|array',
            'documents.agreement_document' => 'nullable|file|max:40960|mimes:pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png',
            'documents.proposal' => 'nullable|file|max:40960|mimes:pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png',
            'documents.signed_agreement' => 'nullable|file|max:40960|mimes:pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png',
            'documents.workplan' => 'nullable|file|max:40960|mimes:pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png',
            'documents.detailed_budget' => 'nullable|file|max:40960|mimes:pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png',
            'documents.amendment' => 'nullable|file|max:40960|mimes:pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png',
        ]);

        $result = DB::transaction(function () use ($data, $donor, $request, $currentGrant) {
            $donor->update(array_merge($data['donor'], ['draft_payload' => null, 'status' => $data['grant']['status'] ?? 'draft']));
            $grant = $currentGrant;
            if (! $grant && ! empty($data['grant']['id'])) {
                $grant = GrantAgreement::withoutGlobalScope('fiscal_year_context')->find($data['grant']['id']);
            }
            if (! $grant && ! empty($data['grant']['grant_no'])) {
                $grant = $donor->grantAgreements()->withoutGlobalScope('fiscal_year_context')->where('grant_no', $data['grant']['grant_no'])->first();
            }
            if (! $grant) {
                $grant = $donor->grantAgreements()->withoutGlobalScope('fiscal_year_context')->first();
            }

            $relationNo = $this->resolveRelationNo($data, $grant);
            [$documents, $agreementPath] = $this->storeUploadedDocuments($request, $grant);
            if ($grant) {
                $grant->update(array_merge($data['grant'], [
                    'donor_id' => $donor->id,
                    'relation_no' => $relationNo,
                    'agreement_document_path' => $agreementPath ?: $grant->agreement_document_path,
                    'supporting_documents' => $documents ?: $grant->supporting_documents,
                ]));
            } else {
                $grant = GrantAgreement::create(array_merge($data['grant'], [
                    'donor_id' => $donor->id,
                    'relation_no' => $relationNo,
                    'agreement_document_path' => $agreementPath,
                    'supporting_documents' => $documents ?: null,
                    'status' => $data['grant']['status'] ?? 'draft',
                    'is_active' => true,
                ]));
            }
            $project = null;
            if (! empty($data['project']['id'])) {
                $project = Project::withoutGlobalScope('fiscal_year_context')->find($data['project']['id']);
            }
            if (! $project && ! empty($data['project']['code'])) {
                $project = $grant->projects()->withoutGlobalScope('fiscal_year_context')->where('code', $data['project']['code'])->first();
            }
            if (! $project) {
                $project = $grant->projects()->withoutGlobalScope('fiscal_year_context')->first();
            }

            if (!empty($data['programme']['id'])) $data['project']['program_id'] = $data['programme']['id'];
            elseif (!empty($data['programme']['code']) && !empty($data['programme']['name'])) $data['project']['program_id'] = Program::firstOrCreate(['code' => $data['programme']['code']], ['name' => $data['programme']['name'], 'objective' => $data['programme']['objective'] ?? null, 'is_active' => true])->id;
            if ($project) {
                // Keep the Integrated Grant bank as the project's default bank
                // unless a project-specific account was explicitly provided.
                $project->update(array_merge($data['project'], [
                    'relation_no' => $relationNo,
                    'bank_account_id' => $data['project']['bank_account_id'] ?? $grant->bank_account_id,
                ]));
            } else {
                $project = Project::create(array_merge($data['project'], [
                    'grant_agreement_id' => $grant->id,
                    'relation_no' => $relationNo,
                    'bank_account_id' => $data['project']['bank_account_id'] ?? $grant->bank_account_id,
                    'budget_currency_id' => $data['project']['budget_currency_id'] ?? $grant->currency_id,
                    'is_active' => true,
                ]));
            }
            $project->partners()->sync(collect($data['project']['partner_ids'] ?? [])->mapWithKeys(fn ($id) => [$id => ['role' => 'implementing_partner']])->all());
            $project->fundingSources()->sync($data['project']['funding_source_ids'] ?? []);
            $fiscalYearId = $this->syncProjectFiscalYears($project, $grant, $request);

            $this->syncFrameworksAndWorkplans($project, $grant, $donor, $relationNo, $data['frameworks'] ?? [], $data['workplans'] ?? [], $fiscalYearId);

            // Preserve existing budget line IDs, Journal references, and Project Budget
            // allocations when updating from the integrated master-data modal.
            if (array_key_exists('budgets', $data)) {
                $existingLines = BudgetLine::withoutGlobalScope('fiscal_year_context')->where('project_id', $project->id)->get();
                $keptIds = [];
                foreach ($data['budgets'] ?? [] as $budget) {
                    $existing = ! empty($budget['id'])
                        ? $existingLines->firstWhere('id', (int) $budget['id'])
                        : $existingLines->firstWhere('line_code', $budget['line_code']);
                    if ($existing) {
                        $updates = $this->budgetLineDefaults([
                            'line_code' => $budget['line_code'],
                            'description' => $budget['description'],
                            'budget_category_id' => $budget['budget_category_id'],
                            'total_amount' => $budget['total_amount'],
                            'project_id' => $project->id,
                            'relation_no' => $relationNo,
                            'grant_agreement_id' => $grant->id,
                            'fiscal_year_id' => $existing->fiscal_year_id ?? $fiscalYearId,
                            'gl_account_id' => $existing->gl_account_id,
                            'unit_price' => ((float) ($existing->quantity ?: 1) === 1.0) ? $budget['total_amount'] : $existing->unit_price,
                            'quantity' => $existing->quantity ?: 1,
                            'period_allocations' => $existing->period_allocations ?: [],
                        ], $grant, $fiscalYearId);
                        $existing->update($updates);
                        $keptIds[] = $existing->id;
                    } else {
                        $createdLine = BudgetLine::create($this->budgetLineDefaults(array_merge($budget, [
                            'project_id' => $project->id,
                            'relation_no' => $relationNo,
                            'grant_agreement_id' => $grant->id,
                            'document_path' => $budget['document_path'] ?? ($documents['detailed_budget'] ?? null),
                        ]), $grant, $fiscalYearId));
                        $keptIds[] = $createdLine->id;
                    }
                }
                $existingLines->whereNotIn('id', $keptIds)->each(function (BudgetLine $line) {
                    $hasTransactions = JournalLine::query()->where('budget_line_id', $line->id)->exists()
                        || BudgetCommitment::query()->where('budget_line_id', $line->id)->exists();
                    if (! $hasTransactions) {
                        $line->delete();
                    }
                });
            }

            if (isset($data['deadlines'])) {
                GrantReportingDeadline::where('grant_agreement_id', $grant->id)->get()->each->delete();
                $today = now()->toDateString();
                foreach ($data['deadlines'] as $deadline) {
                    GrantReportingDeadline::create([
                        'grant_agreement_id' => $grant->id,
                        'report_type' => $deadline['report_type'],
                        'period_label' => $deadline['period_label'] ?? null,
                        'due_date' => $deadline['due_date'],
                        'status' => $deadline['status'] ?? ($deadline['due_date'] < $today ? 'overdue' : 'upcoming'),
                        'notes' => $deadline['notes'] ?? null,
                        'reminder_days' => $deadline['reminder_days'] ?? 7,
                    ]);
                }
            }

            return $donor->fresh()->load($this->integratedRelations());
        });
        return response()->json(['success' => true, 'message' => 'Integrated Donor & Grant record updated.', 'data' => $result]);
    }

    public function destroy(int|string $donor)
    {
        $donorModel = $this->findDonorAcrossFiscalYears($donor);
        DB::transaction(function () use ($donorModel) {
            $grants = $donorModel->grantAgreements()->withoutGlobalScope('fiscal_year_context')->get();
            foreach ($grants as $grant) {
                GrantReportingDeadline::where('grant_agreement_id', $grant->id)->get()->each->delete();
                $projects = $grant->projects()->withoutGlobalScope('fiscal_year_context')->get();
                foreach ($projects as $project) {
                    ProjectLogframe::withoutGlobalScope('fiscal_year_context')->where('project_id', $project->id)->get()->each->delete();
                    ProjectWorkplan::withoutGlobalScope('fiscal_year_context')->where('project_id', $project->id)->get()->each->delete();
                    BudgetLine::withoutGlobalScope('fiscal_year_context')->where('project_id', $project->id)->get()->each->delete();
                    $project->delete();
                }
                $grant->delete();
            }
            $donorModel->delete();
        });
        return response()->json(['success' => true, 'message' => 'Integrated Donor & Grant record deleted.']);
    }

    public function downloadDocument(Request $request)
    {
        $path = (string) $request->query('path', '');
        if ($path === '' || str_contains($path, '..') || str_contains($path, '\\')) {
            return response()->json(['message' => 'Dokumen tidak valid.'], 404);
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return response()->json(['message' => 'Dokumen tidak ditemukan di penyimpanan server.'], 404);
        }

        $absolutePath = $disk->path($path);
        $filename = basename($path);
        return $request->boolean('download')
            ? response()->download($absolutePath, $filename)
            : response()->file($absolutePath);
    }

    public function store(Request $request)
    {
        if ($invalid = $this->mergeMultipartPayload($request)) {
            return $invalid;
        }

        $existingGrantId = $request->input('grant.id');
        if (! $existingGrantId && $request->filled('grant.grant_no')) {
            $existingGrantId = GrantAgreement::withoutGlobalScope('fiscal_year_context')
                ->where('grant_no', $request->input('grant.grant_no'))
                ->value('id');
        }
        if (! $existingGrantId && $request->filled('relation_no')) {
            $existingGrantId = GrantAgreement::withoutGlobalScope('fiscal_year_context')
                ->where('relation_no', $request->input('relation_no'))
                ->value('id');
        }

        $data = $request->validate([
            'relation_no' => ['nullable', 'string', 'max:100', Rule::unique('grant_agreements', 'relation_no')->ignore($existingGrantId)],
            'donor' => 'required|array',
            'donor.id' => 'nullable|integer|exists:donors,id',
            'donor.code' => 'required|string|max:30|unique:donors,code,' . ((int) $request->input('donor.id')),
            'donor.name' => 'required|string|max:150',
            'donor.type' => 'required|string|max:50',
            'donor.lead_applicant' => 'nullable|string|max:150',
            'donor.country' => 'nullable|string|max:100',
            'donor.contact_person' => 'nullable|string|max:100',
            'donor.email' => 'nullable|email|max:150',
            'donor.phone' => 'nullable|string|max:50',
            'grant' => 'required|array',
            'grant.id' => 'nullable|integer',
            'grant.grant_no' => ['required', 'string', 'max:50', Rule::unique('grant_agreements', 'grant_no')->ignore($existingGrantId)],
            'grant.agreement_no' => 'nullable|string|max:50',
            'grant.agreement_date' => 'nullable|date',
            'grant.agreement_name' => 'required|string|max:200',
            'grant.terms_conditions' => 'nullable|string',
            'grant.reporting_period' => 'nullable|string|max:30',
            'grant.donor_fiscal_calendar' => 'nullable|in:JAN_DEC,APR_MAR',
            'grant.donor_id' => 'prohibited',
            'grant.currency_id' => 'required|exists:currencies,id',
            'grant.funding_source_id' => 'nullable|exists:funding_sources,id',
            'grant.bank_account_id' => 'nullable|exists:bank_accounts,id',
            'grant.grant_value' => 'required|numeric|min:0',
            'grant.start_date' => 'required|date',
            'grant.end_date' => 'required|date|after_or_equal:grant.start_date',
            'grant.status' => 'nullable|in:draft,active,closed,suspended',
            'project' => 'required|array',
            'project.code' => ['required', 'string', 'max:30', Rule::unique('projects', 'code')->ignore($currentProject?->id)],
            'project.name' => 'required|string|max:150',
            'project.program_id' => 'nullable|exists:programs,id',
            'project.theme' => 'nullable|string|max:200',
            'project.manager_name' => 'nullable|string|max:100',
            'project.start_date' => 'nullable|date',
            'project.end_date' => 'nullable|date|after_or_equal:project.start_date',
            'project.total_budget' => 'nullable|numeric|min:0',
            'project.partner_ids' => 'nullable|array',
            'project.partner_ids.*' => 'integer|exists:beneficiary_partners,id',
            'project.funding_source_ids' => 'nullable|array',
            'project.funding_source_ids.*' => 'integer|exists:funding_sources,id',
            'programme' => 'nullable|array',
            'programme.id' => 'nullable|integer|exists:programs,id',
            'programme.code' => 'nullable|string|max:50',
            'programme.name' => 'nullable|string|max:150',
            'programme.objective' => 'nullable|string',
            'frameworks' => 'array',
            'frameworks.*.level' => 'required|in:impact,outcome,output,activity',
            'frameworks.*.code' => 'required|string|max:50',
            'frameworks.*.description' => 'required|string',
            'frameworks.*.indicator' => 'nullable|string|max:255',
            'frameworks.*.baseline' => 'nullable|string',
            'frameworks.*.target' => 'nullable|string',
            'workplans' => 'array',
            'workplans.*.activity' => 'required|string|max:255',
            'workplans.*.output_code' => 'nullable|string|max:100',
            'workplans.*.start_date' => 'nullable|date',
            'workplans.*.end_date' => 'nullable|date',
            'workplans.*.status' => 'nullable|string|max:50',
            'workplans.*.baseline' => 'nullable|string',
            'budgets' => 'array',
            'budgets.*.line_code' => 'required|string|max:50',
            'budgets.*.description' => 'required|string|max:255',
            'budgets.*.document_path' => 'nullable|string|max:255',
            'budgets.*.budget_category_id' => 'required|exists:budget_categories,id',
            'budgets.*.total_amount' => 'required|numeric|min:0',
            'budgets.*.activity_id' => 'nullable|exists:activities,id',
            'budgets.*.sub_output_code' => 'nullable|string|max:80',
            'budgets.*.proposal_period' => 'nullable|in:monthly,quarterly,semester,annual',
            'budgets.*.period_allocations' => 'nullable|array',
            'budgets.*.period_allocations.*.period' => 'required_with:budgets.*.period_allocations|string|max:20',
            'budgets.*.period_allocations.*.amount' => 'required_with:budgets.*.period_allocations|numeric|min:0',
            'deadlines' => 'array',
            'deadlines.*.report_type' => 'required|string|max:100',
            'deadlines.*.period_label' => 'nullable|string|max:100',
            'deadlines.*.due_date' => 'required|date',
            'deadlines.*.status' => 'nullable|string|max:50',
            'deadlines.*.notes' => 'nullable|string',
            'deadlines.*.reminder_days' => 'nullable|integer',
            'documents' => 'nullable|array',
            'documents.agreement_document' => 'nullable|file|max:40960|mimes:pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png',
            'documents.proposal' => 'nullable|file|max:40960|mimes:pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png',
            'documents.signed_agreement' => 'nullable|file|max:40960|mimes:pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png',
            'documents.workplan' => 'nullable|file|max:40960|mimes:pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png',
            'documents.detailed_budget' => 'nullable|file|max:40960|mimes:pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png',
            'documents.amendment' => 'nullable|file|max:40960|mimes:pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png',
        ]);

        // MySQL rejects an empty string in nullable DATE/DECIMAL/foreign-key
        // columns. Browsers commonly submit empty optional controls as ''.
        $emptyToNull = static function (array $section, array $fields): array {
            foreach ($fields as $field) {
                if (array_key_exists($field, $section) && $section[$field] === '') {
                    $section[$field] = null;
                }
            }
            return $section;
        };
        $data['grant'] = $emptyToNull($data['grant'], [
            'agreement_no', 'agreement_date', 'funding_source_id', 'bank_account_id', 'reporting_period',
        ]);
        $data['project'] = $emptyToNull($data['project'], [
            'program_id', 'theme', 'manager_name', 'start_date', 'end_date', 'total_budget',
        ]);
        $data['workplans'] = array_map(static function (array $workplan) use ($emptyToNull): array {
            return $emptyToNull($workplan, ['output_code', 'start_date', 'end_date', 'status', 'baseline']);
        }, $data['workplans'] ?? []);
        $data['frameworks'] = array_map(static function (array $framework) use ($emptyToNull): array {
            return $emptyToNull($framework, ['indicator', 'baseline', 'target']);
        }, $data['frameworks'] ?? []);
        $data['budgets'] = array_map(static function (array $budget) use ($emptyToNull): array {
            return $emptyToNull($budget, ['document_path', 'currency_id', 'unit_price', 'quantity', 'exchange_rate']);
        }, $data['budgets'] ?? []);

        try {
            $result = DB::transaction(function () use ($data, $request, $existingGrantId) {
            [$documents, $agreementPath] = $this->storeUploadedDocuments($request);
            $donor = !empty($data['donor']['id']) ? Donor::findOrFail($data['donor']['id']) : Donor::create(array_merge($data['donor'], [
                'status' => $data['donor']['status'] ?? 'active',
                'is_active' => $data['donor']['is_active'] ?? true,
            ]));
            unset($data['donor']['id']);
            if ($donor->wasRecentlyCreated === false) $donor->update(array_merge($data['donor'], ['draft_payload' => null]));
            $grant = $existingGrantId ? GrantAgreement::withoutGlobalScope('fiscal_year_context')->find($existingGrantId) : null;
            $relationNo = $this->resolveRelationNo($data, $grant);
            if ($grant) {
                $grant->update(array_merge($data['grant'], [
                    'donor_id' => $donor->id,
                    'relation_no' => $relationNo,
                    'agreement_document_path' => $agreementPath ?: $grant->agreement_document_path,
                    'supporting_documents' => $documents ?: $grant->supporting_documents,
                    'status' => $data['grant']['status'] ?? 'draft',
                    'is_active' => true,
                ]));
            } else {
                $grant = GrantAgreement::create(array_merge($data['grant'], [
                    'donor_id' => $donor->id,
                    'relation_no' => $relationNo,
                    'agreement_document_path' => $agreementPath,
                    'supporting_documents' => $documents ?: null,
                    'status' => $data['grant']['status'] ?? 'draft',
                    'is_active' => true,
                ]));
            }
            if (!empty($data['programme']['id'])) $data['project']['program_id'] = $data['programme']['id'];
            elseif (!empty($data['programme']['code']) && !empty($data['programme']['name'])) $data['project']['program_id'] = Program::firstOrCreate(['code' => $data['programme']['code']], ['name' => $data['programme']['name'], 'objective' => $data['programme']['objective'] ?? null, 'is_active' => true])->id;
            $project = Project::create(array_merge($data['project'], [
                'grant_agreement_id' => $grant->id,
                'relation_no' => $relationNo,
                'bank_account_id' => $data['project']['bank_account_id'] ?? $grant->bank_account_id,
                'budget_currency_id' => $data['project']['budget_currency_id'] ?? $grant->currency_id,
                'is_active' => true,
            ]));
            $project->partners()->sync(collect($data['project']['partner_ids'] ?? [])->mapWithKeys(fn ($id) => [$id => ['role' => 'implementing_partner']])->all());
            $project->fundingSources()->sync($data['project']['funding_source_ids'] ?? []);
            $fiscalYearId = $this->syncProjectFiscalYears($project, $grant, $request);

            $this->syncFrameworksAndWorkplans($project, $grant, $donor, $relationNo, $data['frameworks'] ?? [], $data['workplans'] ?? [], $fiscalYearId);
            foreach ($data['budgets'] ?? [] as $budget) {
                BudgetLine::create($this->budgetLineDefaults(array_merge($budget, [
                    'project_id' => $project->id,
                    'relation_no' => $relationNo,
                    'grant_agreement_id' => $grant->id,
                    'proposal_period' => $budget['proposal_period'] ?? 'annual',
                    'document_path' => $budget['document_path'] ?? ($documents['detailed_budget'] ?? null),
                ]), $grant, $fiscalYearId));
            }

            $today = now()->toDateString();
            foreach ($data['deadlines'] ?? [] as $deadline) {
                GrantReportingDeadline::create([
                    'grant_agreement_id' => $grant->id,
                    'report_type' => $deadline['report_type'],
                    'period_label' => $deadline['period_label'] ?? null,
                    'due_date' => $deadline['due_date'],
                    'status' => $deadline['status'] ?? ($deadline['due_date'] < $today ? 'overdue' : 'upcoming'),
                    'notes' => $deadline['notes'] ?? null,
                    'reminder_days' => $deadline['reminder_days'] ?? 7,
                ]);
            }

                return $donor->load([
                    'grantAgreements.currency',
                    'grantAgreements.reportingDeadlines',
                    'grantAgreements.projects.logframes',
                    'grantAgreements.projects.workplans',
                    'grantAgreements.projects.budgetLines',
                ]);
            });
        } catch (Throwable $exception) {
            Log::error('Integrated Donor & Grant failed', [
                'message' => $exception->getMessage(),
                'exception' => get_class($exception),
            ]);

            return response()->json([
                'success' => false,
                'message' => app()->hasDebugModeEnabled()
                    ? 'Record Donor & Grant gagal disimpan: '.$exception->getMessage()
                    : 'Record Donor & Grant gagal disimpan. Pastikan migrasi database terbaru sudah dijalankan.',
            ], 422);
        }

        return response()->json(['success' => true, 'message' => 'Integrated Donor & Grant record created.', 'data' => $result], 201);
    }
}
