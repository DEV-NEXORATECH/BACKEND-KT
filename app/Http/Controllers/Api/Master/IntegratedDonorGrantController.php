<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\BudgetLine;
use App\Models\Master\Donor;
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

class IntegratedDonorGrantController extends Controller
{
    private function resolveRelationNo(array $data, ?GrantAgreement $grant = null): string
    {
        $relationNo = trim((string) ($data['relation_no'] ?? $grant?->relation_no ?? ''));

        return $relationNo !== '' ? Str::upper($relationNo) : 'KT-REL-'.Str::upper((string) Str::uuid());
    }

    public function show(Donor $donor)
    {
        return response()->json([
            'success' => true,
            'data' => $donor->load([
                'grantAgreements.currency',
                'grantAgreements.fundingSource',
                'grantAgreements.bankAccount',
                'grantAgreements.reportingDeadlines',
                'grantAgreements.projects.program',
                'grantAgreements.projects.partners',
                'grantAgreements.projects.fundingSources',
                'grantAgreements.projects.logframes',
                'grantAgreements.projects.workplans',
                'grantAgreements.projects.budgetLines',
            ]),
        ]);
    }

    public function destroyProject(Donor $donor, Project $project)
    {
        $belongsToDonor = $donor->grantAgreements()
            ->whereKey($project->grant_agreement_id)
            ->exists();
        if (! $belongsToDonor) {
            return response()->json(['message' => 'Project tidak terhubung ke Funder ini.'], 404);
        }

        $project->delete();
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
        $donor = ! empty($data['donor']['id']) ? Donor::findOrFail($data['donor']['id']) : Donor::create(array_merge($data['donor'], [
            'type' => $data['donor']['type'] ?? 'Other',
            'status' => 'inactive',
            'is_active' => false,
        ]));
        $donor->update(['draft_payload' => $draftPayload, 'status' => 'inactive', 'is_active' => false]);

        return response()->json(['success' => true, 'data' => $donor, 'message' => 'Funder draft berhasil disimpan.'], 201);
    }

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
            'grantAgreements.projects.program',
            'grantAgreements.projects.partners',
            'grantAgreements.projects.fundingSources',
        ])->get();
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
        $rows = [['Donor Code', 'Donor Name', 'Donor Type', 'Lead Applicant / Intermediary', 'Grant No', 'Agreement Name', 'Project Code', 'Project Name', 'Grant Value', 'Status']];
        foreach ($records as $donor) {
            foreach ($donor->grantAgreements as $grant) {
                $projects = $grant->projects->isEmpty() ? [null] : $grant->projects;
                foreach ($projects as $project) {
                    $rows[] = [$donor->code, $donor->name, $donor->type, $donor->lead_applicant, $grant->grant_no, $grant->agreement_name, $project?->code, $project?->name, $grant->grant_value, $grant->status];
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
        $sheet = IOFactory::load($request->file('file')->getRealPath())->getActiveSheet()->toArray(null, true, true, true);
        $headers = array_map(fn ($value) => strtolower(trim((string) $value)), array_shift($sheet) ?: []);
        $records = [];
        foreach ($sheet as $row) {
            $values = array_combine($headers, array_values($row));
            if (!$values || empty($values['donor_code']) || empty($values['grant_no']) || empty($values['project_code'])) continue;
            $records[] = ['donor' => ['code' => $values['donor_code'], 'name' => $values['donor_name'] ?? $values['donor_code'], 'type' => $values['donor_type'] ?? 'Other', 'lead_applicant' => $values['lead_applicant'] ?? $values['lead_applicant_intermediary'] ?? null], 'grant' => ['grant_no' => $values['grant_no'], 'agreement_name' => $values['grant_name'] ?? $values['grant_no'], 'grant_value' => $values['grant_value'] ?? 0, 'currency_id' => $values['currency_id'] ?: DB::table('currencies')->value('id'), 'status' => $values['grant_status'] ?? 'draft', 'start_date' => $values['grant_start_date'] ?? now()->toDateString(), 'end_date' => $values['grant_end_date'] ?? now()->addYear()->toDateString()], 'programme' => ['code' => $values['programme_code'] ?? null, 'name' => $values['programme_name'] ?? null], 'project' => ['code' => $values['project_code'], 'name' => $values['project_name'] ?? $values['project_code'], 'total_budget' => $values['project_budget'] ?? 0], 'frameworks' => [], 'workplans' => [], 'deadlines' => []];
        }
        if (!$records) return response()->json(['message' => 'Template CSV/Excel Donor & Grant Terintegrasi tidak valid atau kosong.'], 422);
        $created = 0;
        $donorId = null;
        foreach ($records as $record) {
            if ($donorId) $record['donor']['id'] = $donorId;
            $response = $this->store(Request::create('/v1/master/donor-grant-integrated', 'POST', $record));
            if ($response->getStatusCode() >= 300) return $response;
            $body = json_decode($response->getContent(), true);
            $donorId = $donorId ?: ($body['data']['id'] ?? $body['data']['data']['id'] ?? null);
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
        $currentGrant = $donor->grantAgreements()->first();
        $data = $request->validate([
            'relation_no' => ['nullable', 'string', 'max:100', Rule::unique('grant_agreements', 'relation_no')->ignore($currentGrant?->id)],
            'donor' => 'required|array', 'donor.code' => 'required|string|max:30', 'donor.name' => 'required|string|max:150', 'donor.type' => 'required|string|max:50', 'donor.lead_applicant' => 'nullable|string|max:150', 'donor.country' => 'nullable|string|max:100', 'donor.contact_person' => 'nullable|string|max:100', 'donor.email' => 'nullable|email|max:150', 'donor.phone' => 'nullable|string|max:50',
            'grant' => 'required|array', 'grant.grant_no' => 'required|string|max:50', 'grant.agreement_no' => 'nullable|string|max:50', 'grant.agreement_date' => 'nullable|date', 'grant.agreement_name' => 'required|string|max:200', 'grant.terms_conditions' => 'nullable|string', 'grant.reporting_period' => 'nullable|string|max:30', 'grant.donor_fiscal_calendar' => 'nullable|in:JAN_DEC,APR_MAR', 'grant.currency_id' => 'required|exists:currencies,id', 'grant.funding_source_id' => 'nullable|exists:funding_sources,id', 'grant.bank_account_id' => 'nullable|exists:bank_accounts,id', 'grant.grant_value' => 'required|numeric|min:0', 'grant.start_date' => 'required|date', 'grant.end_date' => 'required|date|after_or_equal:grant.start_date', 'grant.status' => 'nullable|in:draft,active,closed,suspended',
            'project' => 'required|array', 'project.code' => 'required|string|max:30', 'project.name' => 'required|string|max:150', 'project.program_id' => 'nullable|exists:programs,id', 'project.theme' => 'nullable|string|max:200', 'project.manager_name' => 'nullable|string|max:100', 'project.start_date' => 'nullable|date', 'project.end_date' => 'nullable|date|after_or_equal:project.start_date', 'project.total_budget' => 'nullable|numeric|min:0', 'project.partner_ids' => 'nullable|array', 'project.partner_ids.*' => 'integer|exists:beneficiary_partners,id', 'project.funding_source_ids' => 'nullable|array', 'project.funding_source_ids.*' => 'integer|exists:funding_sources,id',
            'programme' => 'nullable|array', 'programme.id' => 'nullable|integer|exists:programs,id', 'programme.code' => 'nullable|string|max:50', 'programme.name' => 'nullable|string|max:150', 'programme.objective' => 'nullable|string',
            'frameworks' => 'array', 'frameworks.*.level' => 'required|in:impact,outcome,output,activity', 'frameworks.*.code' => 'required|string|max:50', 'frameworks.*.description' => 'required|string', 'frameworks.*.indicator' => 'nullable|string|max:255', 'frameworks.*.baseline' => 'nullable|string', 'frameworks.*.target' => 'nullable|string',
            'workplans' => 'array', 'workplans.*.activity' => 'required|string|max:255', 'workplans.*.output_code' => 'nullable|string|max:100', 'workplans.*.start_date' => 'nullable|date', 'workplans.*.end_date' => 'nullable|date', 'workplans.*.status' => 'nullable|string|max:50', 'workplans.*.baseline' => 'nullable|string',
            'budgets' => 'array', 'budgets.*.line_code' => 'required|string|max:50', 'budgets.*.description' => 'required|string|max:255', 'budgets.*.budget_category_id' => 'required|exists:budget_categories,id', 'budgets.*.total_amount' => 'required|numeric|min:0',
            'deadlines' => 'array', 'deadlines.*.report_type' => 'required|string|max:100', 'deadlines.*.period_label' => 'nullable|string|max:100', 'deadlines.*.due_date' => 'required|date', 'deadlines.*.status' => 'nullable|string|max:50', 'deadlines.*.notes' => 'nullable|string', 'deadlines.*.reminder_days' => 'nullable|integer',
        ]);

        $result = DB::transaction(function () use ($data, $donor) {
            $donor->update(array_merge($data['donor'], ['draft_payload' => null, 'status' => $data['grant']['status'] ?? 'draft']));
            $grant = $donor->grantAgreements()->first();
            $relationNo = $this->resolveRelationNo($data, $grant);
            if ($grant) {
                $grant->update(array_merge($data['grant'], ['donor_id' => $donor->id, 'relation_no' => $relationNo]));
            } else {
                $grant = GrantAgreement::create(array_merge($data['grant'], [
                    'donor_id' => $donor->id,
                    'relation_no' => $relationNo,
                    'status' => $data['grant']['status'] ?? 'draft',
                    'is_active' => true,
                ]));
            }
            $project = $grant->projects()->first();
            if (!empty($data['programme']['id'])) $data['project']['program_id'] = $data['programme']['id'];
            elseif (!empty($data['programme']['code']) && !empty($data['programme']['name'])) $data['project']['program_id'] = Program::firstOrCreate(['code' => $data['programme']['code']], ['name' => $data['programme']['name'], 'objective' => $data['programme']['objective'] ?? null, 'is_active' => true])->id;
            if ($project) {
                $project->update(array_merge($data['project'], ['relation_no' => $relationNo]));
            } else {
                $project = Project::create(array_merge($data['project'], [
                    'grant_agreement_id' => $grant->id,
                    'relation_no' => $relationNo,
                    'budget_currency_id' => $data['project']['budget_currency_id'] ?? $grant->currency_id,
                    'is_active' => true,
                ]));
            }
            $project->partners()->sync(collect($data['project']['partner_ids'] ?? [])->mapWithKeys(fn ($id) => [$id => ['role' => 'implementing_partner']])->all());
            $project->fundingSources()->sync($data['project']['funding_source_ids'] ?? []);

            ProjectLogframe::where('project_id', $project->id)->get()->each->delete();
            ProjectWorkplan::where('project_id', $project->id)->get()->each->delete();
            foreach (collect($data['frameworks'] ?? [])->unique('code')->values() as $framework) {
                ProjectLogframe::create(array_merge($framework, ['project_id' => $project->id, 'relation_no' => $relationNo]));
            }
            foreach ($data['workplans'] ?? [] as $workplan) ProjectWorkplan::create(array_merge($workplan, ['project_id' => $project->id, 'relation_no' => $relationNo, 'grant_agreement_id' => $grant->id, 'donor_id' => $donor->id]));
            // Budget is maintained in the dedicated Project Budget editor.
            // Only legacy callers that explicitly send budgets may replace
            // them; normal master-data edits must never delete budget history.
            if (array_key_exists('budgets', $data)) {
                BudgetLine::where('project_id', $project->id)->get()->each->delete();
                foreach ($data['budgets'] ?? [] as $budget) {
                    BudgetLine::create($this->budgetLineDefaults(array_merge($budget, ['project_id' => $project->id, 'relation_no' => $relationNo, 'grant_agreement_id' => $grant->id]), $grant));
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
            'relation_no' => 'nullable|string|max:100|unique:grant_agreements,relation_no',
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
            $donor = !empty($data['donor']['id']) ? Donor::findOrFail($data['donor']['id']) : Donor::create(array_merge($data['donor'], [
                'status' => $data['donor']['status'] ?? 'active',
                'is_active' => $data['donor']['is_active'] ?? true,
            ]));
            unset($data['donor']['id']);
            if ($donor->wasRecentlyCreated === false) $donor->update(array_merge($data['donor'], ['draft_payload' => null]));
            $relationNo = $this->resolveRelationNo($data);
            $grant = GrantAgreement::create(array_merge($data['grant'], [
                'donor_id' => $donor->id,
                'relation_no' => $relationNo,
                'agreement_document_path' => $agreementFile?->store('donor-grant-documents', 'public'),
                'supporting_documents' => $documents ?: null,
                'status' => $data['grant']['status'] ?? 'draft',
                'is_active' => true,
            ]));
            if (!empty($data['programme']['id'])) $data['project']['program_id'] = $data['programme']['id'];
            elseif (!empty($data['programme']['code']) && !empty($data['programme']['name'])) $data['project']['program_id'] = Program::firstOrCreate(['code' => $data['programme']['code']], ['name' => $data['programme']['name'], 'objective' => $data['programme']['objective'] ?? null, 'is_active' => true])->id;
            $project = Project::create(array_merge($data['project'], [
                'grant_agreement_id' => $grant->id,
                'relation_no' => $relationNo,
                'budget_currency_id' => $data['project']['budget_currency_id'] ?? $grant->currency_id,
                'is_active' => true,
            ]));
            $project->partners()->sync(collect($data['project']['partner_ids'] ?? [])->mapWithKeys(fn ($id) => [$id => ['role' => 'implementing_partner']])->all());
            $project->fundingSources()->sync($data['project']['funding_source_ids'] ?? []);

            foreach ($data['frameworks'] ?? [] as $framework) {
                ProjectLogframe::create(array_merge($framework, ['project_id' => $project->id, 'relation_no' => $relationNo]));
            }
            foreach ($data['workplans'] ?? [] as $workplan) {
                ProjectWorkplan::create(array_merge($workplan, [
                    'project_id' => $project->id,
                    'relation_no' => $relationNo,
                    'grant_agreement_id' => $grant->id,
                    'donor_id' => $donor->id,
                ]));
            }
            foreach ($data['budgets'] ?? [] as $budget) {
                BudgetLine::create($this->budgetLineDefaults(array_merge($budget, [
                    'project_id' => $project->id,
                    'relation_no' => $relationNo,
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
