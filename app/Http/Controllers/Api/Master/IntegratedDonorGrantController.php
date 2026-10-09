<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\BudgetLine;
use App\Models\Master\Donor;
use App\Models\Master\GrantAgreement;
use App\Models\Master\Project;
use App\Models\Master\ProjectLogframe;
use App\Models\Master\ProjectWorkplan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class IntegratedDonorGrantController extends Controller
{
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
            'documents' => 'nullable|array',
            'documents.agreement_document' => 'nullable|file|max:40960|mimes:pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png',
            'documents.proposal' => 'nullable|file|max:40960|mimes:pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png',
            'documents.signed_agreement' => 'nullable|file|max:40960|mimes:pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png',
            'documents.workplan' => 'nullable|file|max:40960|mimes:pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png',
            'documents.detailed_budget' => 'nullable|file|max:40960|mimes:pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png',
            'documents.amendment' => 'nullable|file|max:40960|mimes:pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png',
        ]);

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
                BudgetLine::create(array_merge($budget, [
                    'project_id' => $project->id,
                    'grant_agreement_id' => $grant->id,
                    'currency_id' => $budget['currency_id'] ?? $grant->currency_id,
                    'proposal_period' => $budget['proposal_period'] ?? 'annual',
                    'document_path' => $budget['document_path'] ?? ($documents['detailed_budget'] ?? null),
                    'unit_price' => $budget['unit_price'] ?? $budget['total_amount'],
                    'quantity' => $budget['quantity'] ?? 1,
                    'is_active' => true,
                ]));
            }

                return $donor->load([
                    'grantAgreements.currency',
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
