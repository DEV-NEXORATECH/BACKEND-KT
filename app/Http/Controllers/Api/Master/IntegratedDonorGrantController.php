<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\BudgetLine;
use App\Models\Master\Donor;
use App\Models\Master\GrantAgreement;
use App\Models\Master\Project;
use App\Models\Master\ProjectLogframe;
use App\Models\Master\ProjectWorkplan;
use App\Models\Master\BudgetCategory;
use App\Models\Master\ChartOfAccount;
use App\Models\Master\GrantReportingDeadline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class IntegratedDonorGrantController extends Controller
{
    public function export(Request $request)
    {
        $records = Donor::with([
            'grantAgreements.currency',
            'grantAgreements.fundingSource',
            'grantAgreements.bankAccount',
            'grantAgreements.reportingDeadlines',
            'grantAgreements.projects.logframes',
            'grantAgreements.projects.workplans',
            'grantAgreements.projects.budgetLines',
        ])->get();
        if ($request->query('format') === 'json') {
            $payload = $records->map(function ($donor) {
                $grant = $donor->grantAgreements->first();
                $project = $grant?->projects?->first();
                return [
                    'donor' => $donor->only(['code', 'name', 'type', 'country', 'contact_person', 'email', 'phone']),
                    'grant' => $grant ? array_merge($grant->only(['grant_no', 'agreement_no', 'agreement_date', 'agreement_name', 'terms_conditions', 'reporting_period', 'donor_fiscal_calendar', 'grant_value', 'start_date', 'end_date', 'status']), [
                        'currency_id' => $grant->currency_id,
                        'funding_source_id' => $grant->funding_source_id,
                        'bank_account_id' => $grant->bank_account_id,
                    ]) : [],
                    'project' => $project ? $project->only(['code', 'name', 'program_id', 'theme', 'manager_name', 'start_date', 'end_date', 'total_budget']) : [],
                    'frameworks' => $project?->logframes?->map->only(['level', 'code', 'description', 'indicator', 'baseline', 'target'])->values() ?? [],
                    'workplans' => $project?->workplans?->map->only(['output_code', 'activity', 'start_date', 'end_date', 'status', 'baseline'])->values() ?? [],
                    'budgets' => $project?->budgetLines?->map->only(['line_code', 'description', 'budget_category_id', 'total_amount'])->values() ?? [],
                    'deadlines' => $grant?->reportingDeadlines?->map->only(['report_type', 'period_label', 'due_date', 'status', 'notes', 'reminder_days'])->values() ?? [],
                ];
            })->values();
            return response()->json(['template_version' => 'donor-grant-integrated-v1', 'records' => $payload]);
        }
        $rows = [['Donor Code', 'Donor Name', 'Donor Type', 'Grant No', 'Agreement Name', 'Project Code', 'Project Name', 'Grant Value', 'Status']];
        foreach ($records as $donor) {
            foreach ($donor->grantAgreements as $grant) {
                $projects = $grant->projects->isEmpty() ? [null] : $grant->projects;
                foreach ($projects as $project) {
                    $rows[] = [$donor->code, $donor->name, $donor->type, $grant->grant_no, $grant->agreement_name, $project?->code, $project?->name, $grant->grant_value, $grant->status];
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
        $request->validate(['file' => 'required|file|mimes:json,txt|max:20480']);
        $payload = json_decode($request->file('file')->get(), true);
        if (!is_array($payload) || ($payload['template_version'] ?? null) !== 'donor-grant-integrated-v1' || empty($payload['records'])) {
            return response()->json(['message' => 'Template Donor & Grant Terintegrasi tidak valid.'], 422);
        }
        $created = 0;
        foreach ($payload['records'] as $record) {
            $response = $this->store(Request::create('/v1/master/donor-grant-integrated', 'POST', $record));
            if ($response->getStatusCode() >= 300) return $response;
            $created++;
        }
        return response()->json(['success' => true, 'message' => 'Import Donor & Grant Terintegrasi berhasil.', 'created' => $created]);
    }

    private function budgetLineDefaults(array $budget, GrantAgreement $grant): array
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
            'currency_id' => $budget['currency_id'] ?? $grant->currency_id,
            'proposal_period' => $budget['proposal_period'] ?? 'annual',
            'unit_price' => $budget['unit_price'] ?? $budget['total_amount'],
            'quantity' => $budget['quantity'] ?? 1,
            'period_allocations' => $budget['period_allocations'] ?? [],
            'is_active' => true,
        ]);
    }

    public function update(Request $request, Donor $donor)
    {
        $data = $request->validate([
            'donor' => 'required|array', 'donor.code' => 'required|string|max:30', 'donor.name' => 'required|string|max:150', 'donor.type' => 'required|string|max:50', 'donor.country' => 'nullable|string|max:100', 'donor.contact_person' => 'nullable|string|max:100', 'donor.email' => 'nullable|email|max:150', 'donor.phone' => 'nullable|string|max:50',
            'grant' => 'required|array', 'grant.grant_no' => 'required|string|max:50', 'grant.agreement_no' => 'nullable|string|max:50', 'grant.agreement_date' => 'nullable|date', 'grant.agreement_name' => 'required|string|max:200', 'grant.terms_conditions' => 'nullable|string', 'grant.reporting_period' => 'nullable|string|max:30', 'grant.donor_fiscal_calendar' => 'nullable|in:JAN_DEC,APR_MAR', 'grant.currency_id' => 'required|exists:currencies,id', 'grant.funding_source_id' => 'nullable|exists:funding_sources,id', 'grant.bank_account_id' => 'nullable|exists:bank_accounts,id', 'grant.grant_value' => 'required|numeric|min:0', 'grant.start_date' => 'required|date', 'grant.end_date' => 'required|date|after_or_equal:grant.start_date', 'grant.status' => 'nullable|in:draft,active,closed,suspended',
            'project' => 'required|array', 'project.code' => 'required|string|max:30', 'project.name' => 'required|string|max:150', 'project.program_id' => 'nullable|exists:programs,id', 'project.theme' => 'nullable|string|max:200', 'project.manager_name' => 'nullable|string|max:100', 'project.start_date' => 'nullable|date', 'project.end_date' => 'nullable|date|after_or_equal:project.start_date', 'project.total_budget' => 'nullable|numeric|min:0',
            'frameworks' => 'array', 'frameworks.*.level' => 'required|in:impact,outcome,output,activity', 'frameworks.*.code' => 'required|string|max:50', 'frameworks.*.description' => 'required|string', 'frameworks.*.indicator' => 'nullable|string|max:255', 'frameworks.*.baseline' => 'nullable|numeric', 'frameworks.*.target' => 'nullable|numeric',
            'workplans' => 'array', 'workplans.*.activity' => 'required|string|max:255', 'workplans.*.output_code' => 'nullable|string|max:100', 'workplans.*.start_date' => 'nullable|date', 'workplans.*.end_date' => 'nullable|date', 'workplans.*.status' => 'nullable|string|max:50', 'workplans.*.baseline' => 'nullable|numeric',
            'budgets' => 'array', 'budgets.*.line_code' => 'required|string|max:50', 'budgets.*.description' => 'required|string|max:255', 'budgets.*.budget_category_id' => 'required|exists:budget_categories,id', 'budgets.*.total_amount' => 'required|numeric|min:0',
            'deadlines' => 'array', 'deadlines.*.report_type' => 'required|string|max:100', 'deadlines.*.period_label' => 'nullable|string|max:100', 'deadlines.*.due_date' => 'required|date', 'deadlines.*.status' => 'nullable|string|max:50', 'deadlines.*.notes' => 'nullable|string', 'deadlines.*.reminder_days' => 'nullable|integer',
        ]);

        $result = DB::transaction(function () use ($data, $donor) {
            $donor->update($data['donor']);
            $grant = $donor->grantAgreements()->firstOrFail();
            $grant->update(array_merge($data['grant'], ['donor_id' => $donor->id]));
            $project = $grant->projects()->firstOrFail();
            $project->update($data['project']);

            ProjectLogframe::where('project_id', $project->id)->get()->each->delete();
            ProjectWorkplan::where('project_id', $project->id)->get()->each->delete();
            foreach ($data['frameworks'] ?? [] as $framework) ProjectLogframe::create(array_merge($framework, ['project_id' => $project->id]));
            foreach ($data['workplans'] ?? [] as $workplan) ProjectWorkplan::create(array_merge($workplan, ['project_id' => $project->id, 'grant_agreement_id' => $grant->id, 'donor_id' => $donor->id]));
            // Budget is maintained in the dedicated Project Budget editor.
            // Only legacy callers that explicitly send budgets may replace
            // them; normal master-data edits must never delete budget history.
            if (array_key_exists('budgets', $data)) {
                BudgetLine::where('project_id', $project->id)->get()->each->delete();
                foreach ($data['budgets'] ?? [] as $budget) {
                    BudgetLine::create($this->budgetLineDefaults(array_merge($budget, ['project_id' => $project->id, 'grant_agreement_id' => $grant->id]), $grant));
                }
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

            return $donor->fresh()->load(['grantAgreements.currency', 'grantAgreements.reportingDeadlines', 'grantAgreements.projects.logframes', 'grantAgreements.projects.workplans', 'grantAgreements.projects.budgetLines']);
        });
        return response()->json(['success' => true, 'message' => 'Integrated Donor & Grant record updated.', 'data' => $result]);
    }

    public function destroy(Donor $donor)
    {
        DB::transaction(function () use ($donor) {
            foreach ($donor->grantAgreements as $grant) {
                GrantReportingDeadline::where('grant_agreement_id', $grant->id)->get()->each->delete();
                foreach ($grant->projects as $project) {
                    ProjectLogframe::where('project_id', $project->id)->get()->each->delete();
                    ProjectWorkplan::where('project_id', $project->id)->get()->each->delete();
                    BudgetLine::where('project_id', $project->id)->get()->each->delete();
                    $project->delete();
                }
                $grant->delete();
            }
            $donor->delete();
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
        if ($request->filled('payload')) {
            $payload = json_decode((string) $request->input('payload'), true);
            if (! is_array($payload)) {
                return response()->json(['message' => 'Payload integrated record tidak valid.'], 422);
            }
            $request->merge($payload);
        }

        $data = $request->validate([
            'donor' => 'required|array',
            'donor.code' => 'required|string|max:30|unique:donors,code',
            'donor.name' => 'required|string|max:150',
            'donor.type' => 'required|string|max:50',
            'donor.country' => 'nullable|string|max:100',
            'donor.contact_person' => 'nullable|string|max:100',
            'donor.email' => 'nullable|email|max:150',
            'donor.phone' => 'nullable|string|max:50',
            'grant' => 'required|array',
            'grant.grant_no' => 'required|string|max:50|unique:grant_agreements,grant_no',
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
            'project.code' => 'required|string|max:30|unique:projects,code',
            'project.name' => 'required|string|max:150',
            'project.program_id' => 'nullable|exists:programs,id',
            'project.theme' => 'nullable|string|max:200',
            'project.manager_name' => 'nullable|string|max:100',
            'project.start_date' => 'nullable|date',
            'project.end_date' => 'nullable|date|after_or_equal:project.start_date',
            'project.total_budget' => 'nullable|numeric|min:0',
            'frameworks' => 'array',
            'frameworks.*.level' => 'required|in:impact,outcome,output,activity',
            'frameworks.*.code' => 'required|string|max:50',
            'frameworks.*.description' => 'required|string',
            'frameworks.*.indicator' => 'nullable|string|max:255',
            'frameworks.*.baseline' => 'nullable|numeric',
            'frameworks.*.target' => 'nullable|numeric',
            'workplans' => 'array',
            'workplans.*.activity' => 'required|string|max:255',
            'workplans.*.output_code' => 'nullable|string|max:100',
            'workplans.*.start_date' => 'nullable|date',
            'workplans.*.end_date' => 'nullable|date',
            'workplans.*.status' => 'nullable|string|max:50',
            'workplans.*.baseline' => 'nullable|numeric',
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
            $result = DB::transaction(function () use ($data) {
            $documents = [];
            foreach (['proposal', 'signed_agreement', 'workplan', 'detailed_budget', 'amendment'] as $documentKey) {
                $file = request()->file("documents.{$documentKey}");
                if ($file) {
                    if (! $file->isValid()) {
                        throw new \RuntimeException("Upload dokumen {$documentKey} gagal: ".$file->getErrorMessage());
                    }
                    $documents[$documentKey] = $file->store('donor-grant-documents', 'public');
                }
            }
            $agreementFile = request()->file('documents.agreement_document');
            if ($agreementFile && ! $agreementFile->isValid()) {
                throw new \RuntimeException('Upload Dokumen Agreement gagal: '.$agreementFile->getErrorMessage());
            }
            $donor = Donor::create(array_merge($data['donor'], [
                'status' => $data['donor']['status'] ?? 'active',
                'is_active' => $data['donor']['is_active'] ?? true,
            ]));
            $grant = GrantAgreement::create(array_merge($data['grant'], [
                'donor_id' => $donor->id,
                'agreement_document_path' => $agreementFile?->store('donor-grant-documents', 'public'),
                'supporting_documents' => $documents ?: null,
                'status' => $data['grant']['status'] ?? 'draft',
                'is_active' => true,
            ]));
            $project = Project::create(array_merge($data['project'], [
                'grant_agreement_id' => $grant->id,
                'budget_currency_id' => $data['project']['budget_currency_id'] ?? $grant->currency_id,
                'is_active' => true,
            ]));

            foreach ($data['frameworks'] ?? [] as $framework) {
                ProjectLogframe::create(array_merge($framework, ['project_id' => $project->id]));
            }
            foreach ($data['workplans'] ?? [] as $workplan) {
                ProjectWorkplan::create(array_merge($workplan, [
                    'project_id' => $project->id,
                    'grant_agreement_id' => $grant->id,
                    'donor_id' => $donor->id,
                ]));
            }
            foreach ($data['budgets'] ?? [] as $budget) {
                BudgetLine::create($this->budgetLineDefaults(array_merge($budget, [
                    'project_id' => $project->id,
                    'grant_agreement_id' => $grant->id,
                    'proposal_period' => $budget['proposal_period'] ?? 'annual',
                    'document_path' => $budget['document_path'] ?? ($documents['detailed_budget'] ?? null),
                ]), $grant));
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
