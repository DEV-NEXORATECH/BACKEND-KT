<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\GrantAgreement;
use App\Models\Master\GrantDisbursementRequest;
use App\Models\Master\GrantForecastItem;
use App\Models\Master\BudgetLine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GrantDisbursementRequestController extends Controller
{
    /**
     * List disbursement requests with summary and filters
     */
    public function index(Request $request)
    {
        $query = GrantDisbursementRequest::with([
            'grantAgreement:id,grant_no,agreement_no,agreement_name,donor_id,currency_id,grant_value,exchange_rate_contract,bank_account_id',
            'grantAgreement.donor:id,code,name,type',
            'grantAgreement.currency:id,code,name',
            'currency:id,code,name',
            'bankAccount:id,bank_name,account_number,account_name',
            'receivedBankAccount:id,bank_name,account_number,account_name',
        ]);

        if ($request->filled('grant_agreement_id')) {
            $query->where('grant_agreement_id', $request->input('grant_agreement_id'));
        }

        if ($request->filled('status') && $request->input('status') !== 'ALL') {
            $query->where('status', strtolower($request->input('status')));
        }

        if ($request->filled('period_label') && $request->input('period_label') !== 'ALL') {
            $query->where('period_label', $request->input('period_label'));
        }

        if ($request->filled('year')) {
            $query->whereYear('request_date', $request->input('year'));
        }

        if ($request->filled('search')) {
            $search = '%' . trim($request->input('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('request_no', 'like', $search)
                  ->orWhere('period_label', 'like', $search)
                  ->orWhere('notes', 'like', $search)
                  ->orWhereHas('grantAgreement', function ($gq) use ($search) {
                      $gq->where('grant_no', 'like', $search)
                         ->orWhere('agreement_name', 'like', $search)
                         ->orWhereHas('donor', function ($dq) use ($search) {
                             $dq->where('name', 'like', $search)->orWhere('code', 'like', $search);
                         });
                  });
            });
        }

        $allRequests = (clone $query)->get();

        // Calculate KPIs
        $totalRequested = (float) $allRequests->sum('requested_amount');
        $totalReceived = (float) $allRequests->where('status', 'received')->sum('received_amount');
        $totalReceivedIdr = (float) $allRequests->where('status', 'received')->sum('received_amount_idr');
        $netFxGainLoss = (float) $allRequests->where('status', 'received')->sum('fx_gain_loss');
        $pendingCount = $allRequests->whereIn('status', ['draft', 'submitted', 'approved'])->count();

        $perPage = (int) $request->input('per_page', 10);
        $paginated = $query->orderBy('request_date', 'desc')->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'summary' => [
                'total_requests' => $allRequests->count(),
                'total_requested_amount' => round($totalRequested, 2),
                'total_received_amount' => round($totalReceived, 2),
                'total_received_idr' => round($totalReceivedIdr, 2),
                'net_fx_gain_loss' => round($netFxGainLoss, 2),
                'pending_count' => $pendingCount,
                'received_count' => $allRequests->where('status', 'received')->count(),
            ],
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Generate baseline quarterly forecast lines from BudgetLines & historical expenses
     */
    public function baseline(Request $request)
    {
        $grantId = $request->input('grant_agreement_id');
        $quarter = $request->input('quarter', 'Q1'); // Q1, Q2, Q3, Q4

        if (!$grantId) {
            return response()->json([
                'success' => false,
                'message' => 'grant_agreement_id wajib diisi.',
            ], 422);
        }

        $grant = GrantAgreement::with(['donor', 'currency', 'bankAccount'])->findOrFail($grantId);

        $budgetLines = BudgetLine::with(['activity', 'glAccount', 'currency'])
            ->where('grant_agreement_id', $grantId)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $contractRate = (float) ($grant->exchange_rate_contract ?: 1);

        $items = $budgetLines->map(function ($line) use ($quarter, $contractRate) {
            $allocations = is_array($line->period_allocations) ? $line->period_allocations : [];

            // Extract quarterly budget allocation if specified, otherwise 1/4 of total
            $proposalBudget = 0;
            if (isset($allocations[$quarter]) && is_numeric($allocations[$quarter])) {
                $proposalBudget = (float) $allocations[$quarter];
            } else {
                $proposalBudget = round((float) ($line->total_amount ?: 0) / 4, 2);
            }

            // Estimate historical expense (baseline trend)
            // If expense lines exist for this budget line or activity, aggregate here:
            $historicalExpense = 0;
            try {
                $historicalExpense = (float) DB::table('expense_request_lines')
                    ->where('budget_line_id', $line->id)
                    ->sum('amount');
            } catch (\Throwable $e) {
                $historicalExpense = 0;
            }

            return [
                'budget_line_id' => $line->id,
                'line_code' => $line->line_code,
                'description' => $line->description,
                'sub_output_code' => $line->sub_output_code,
                'sub_output_name' => $line->sub_output_name ?: 'Sub-Output Project',
                'activity_code' => $line->activity_code ?: ($line->activity->code ?? null),
                'activity_name' => $line->activity_name ?: ($line->activity->name ?? 'Aktivitas Terkait'),
                'cost_category' => $line->glAccount ? "{$line->glAccount->account_code} - {$line->glAccount->account_name}" : 'General Program Costs',
                'gl_account_id' => $line->gl_account_id,
                'currency_code' => $line->currency->code ?? 'IDR',
                'proposal_budget' => round($proposalBudget, 2),
                'historical_expense' => round($historicalExpense, 2),
                'adjustment_type' => 'percentage',
                'adjustment_percent' => 0,
                'adjustment_idr' => 0,
                'forecast_amount' => round($proposalBudget, 2),
                'notes' => '',
            ];
        });

        return response()->json([
            'success' => true,
            'grant' => [
                'id' => $grant->id,
                'grant_no' => $grant->grant_no,
                'agreement_name' => $grant->agreement_name,
                'donor_name' => $grant->donor->name ?? '—',
                'currency_code' => $grant->currency->code ?? 'IDR',
                'contract_fx_rate' => $contractRate,
                'bank_account' => $grant->bankAccount ? [
                    'id' => $grant->bankAccount->id,
                    'bank_name' => $grant->bankAccount->bank_name,
                    'account_number' => $grant->bankAccount->account_number,
                    'account_name' => $grant->bankAccount->account_name,
                ] : null,
            ],
            'quarter' => $quarter,
            'items' => $items,
            'total_proposal' => round($items->sum('proposal_budget'), 2),
            'total_historical' => round($items->sum('historical_expense'), 2),
            'total_forecast' => round($items->sum('forecast_amount'), 2),
        ]);
    }

    /**
     * Store a new disbursement request
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'grant_agreement_id' => 'required|exists:grant_agreements,id',
            'period_type' => 'nullable|string|max:32',
            'period_label' => 'required|string|max:64',
            'request_date' => 'required|date',
            'currency_id' => 'nullable|exists:currencies,id',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'forecast_total_amount' => 'nullable|numeric|min:0',
            'requested_amount' => 'required|numeric|min:0',
            'contract_fx_rate' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'attachment' => 'nullable|file|max:10240',
            'items' => 'nullable|array',
        ]);

        $grant = GrantAgreement::findOrFail($validated['grant_agreement_id']);
        $contractRate = (float) ($validated['contract_fx_rate'] ?? ($grant->exchange_rate_contract ?: 1));
        $requestedAmount = (float) $validated['requested_amount'];
        $requestedAmountIdr = round($requestedAmount * $contractRate, 2);

        // Auto-generate request_no if empty
        $requestNo = $request->input('request_no');
        if (!$requestNo) {
            $prefix = 'DR-' . preg_replace('/[^A-Za-z0-9]/', '', $grant->grant_no);
            $count = GrantDisbursementRequest::where('grant_agreement_id', $grant->id)->count() + 1;
            $requestNo = "{$prefix}-" . str_replace(' ', '', $validated['period_label']) . "-{$count}";
        }

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('disbursement-requests', 'public');
        }

        return DB::transaction(function () use ($validated, $grant, $requestNo, $contractRate, $requestedAmount, $requestedAmountIdr, $attachmentPath, $request) {
            $disbursement = GrantDisbursementRequest::create([
                'grant_agreement_id' => $grant->id,
                'bank_account_id' => $validated['bank_account_id'] ?? $grant->bank_account_id,
                'request_no' => $requestNo,
                'period_type' => $validated['period_type'] ?? 'quarterly',
                'period_label' => $validated['period_label'],
                'request_date' => $validated['request_date'],
                'currency_id' => $validated['currency_id'] ?? $grant->currency_id,
                'forecast_total_amount' => $validated['forecast_total_amount'] ?? $requestedAmount,
                'requested_amount' => $requestedAmount,
                'contract_fx_rate' => $contractRate,
                'requested_amount_idr' => $requestedAmountIdr,
                'status' => 'draft',
                'notes' => $validated['notes'] ?? null,
                'attachment_path' => $attachmentPath,
                'created_by' => auth()->id(),
            ]);

            // Save individual forecast lines if provided
            $items = $request->input('items', []);
            if (is_array($items) && count($items) > 0) {
                foreach ($items as $item) {
                    GrantForecastItem::create([
                        'disbursement_request_id' => $disbursement->id,
                        'grant_agreement_id' => $grant->id,
                        'budget_line_id' => $item['budget_line_id'] ?? null,
                        'period_label' => $validated['period_label'],
                        'sub_output_code' => $item['sub_output_code'] ?? null,
                        'sub_output_name' => $item['sub_output_name'] ?? null,
                        'activity_code' => $item['activity_code'] ?? null,
                        'activity_name' => $item['activity_name'] ?? null,
                        'cost_category' => $item['cost_category'] ?? null,
                        'proposal_budget' => $item['proposal_budget'] ?? 0,
                        'historical_expense' => $item['historical_expense'] ?? 0,
                        'adjustment_type' => $item['adjustment_type'] ?? 'percentage',
                        'adjustment_percent' => $item['adjustment_percent'] ?? 0,
                        'adjustment_idr' => $item['adjustment_idr'] ?? 0,
                        'forecast_amount' => $item['forecast_amount'] ?? 0,
                        'notes' => $item['notes'] ?? null,
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Permintaan pencairan dana (Disbursement Request) berhasil disimpan sebagai draft.',
                'data' => $disbursement->load(['grantAgreement.donor', 'currency', 'bankAccount', 'forecastItems']),
            ], 201);
        });
    }

    /**
     * Show detail of disbursement request
     */
    public function show($id)
    {
        $disbursement = GrantDisbursementRequest::with([
            'grantAgreement:id,grant_no,agreement_no,agreement_name,donor_id,currency_id,grant_value,exchange_rate_contract,bank_account_id',
            'grantAgreement.donor:id,code,name,type',
            'grantAgreement.currency:id,code,name',
            'currency:id,code,name',
            'bankAccount:id,bank_name,account_number,account_name',
            'receivedBankAccount:id,bank_name,account_number,account_name',
            'forecastItems',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $disbursement,
        ]);
    }

    /**
     * Update disbursement request
     */
    public function update(Request $request, $id)
    {
        $disbursement = GrantDisbursementRequest::findOrFail($id);

        if ($disbursement->status === 'received') {
            return response()->json([
                'success' => false,
                'message' => 'Permintaan yang sudah berstatus Received tidak dapat diubah.',
            ], 422);
        }

        $validated = $request->validate([
            'period_type' => 'nullable|string|max:32',
            'period_label' => 'sometimes|required|string|max:64',
            'request_date' => 'sometimes|required|date',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'requested_amount' => 'sometimes|required|numeric|min:0',
            'contract_fx_rate' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'attachment' => 'nullable|file|max:10240',
        ]);

        if ($request->hasFile('attachment')) {
            if ($disbursement->attachment_path && Storage::disk('public')->exists($disbursement->attachment_path)) {
                Storage::disk('public')->delete($disbursement->attachment_path);
            }
            $validated['attachment_path'] = $request->file('attachment')->store('disbursement-requests', 'public');
        }

        if (isset($validated['requested_amount'])) {
            $rate = (float) ($validated['contract_fx_rate'] ?? $disbursement->contract_fx_rate);
            $validated['requested_amount_idr'] = round((float) $validated['requested_amount'] * $rate, 2);
        }

        $validated['updated_by'] = auth()->id();
        $disbursement->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Disbursement Request berhasil diperbarui.',
            'data' => $disbursement->fresh(['grantAgreement.donor', 'currency', 'bankAccount', 'forecastItems']),
        ]);
    }

    /**
     * Submit disbursement request to donor
     */
    public function submit($id)
    {
        $disbursement = GrantDisbursementRequest::findOrFail($id);
        $disbursement->update(['status' => 'submitted', 'updated_by' => auth()->id()]);

        return response()->json([
            'success' => true,
            'message' => "Permintaan pencairan dana ({$disbursement->request_no}) telah diajukan ke donor.",
            'data' => $disbursement,
        ]);
    }

    /**
     * Approve disbursement request by donor
     */
    public function approve($id)
    {
        $disbursement = GrantDisbursementRequest::findOrFail($id);
        $disbursement->update(['status' => 'approved', 'updated_by' => auth()->id()]);

        return response()->json([
            'success' => true,
            'message' => "Permintaan pencairan dana ({$disbursement->request_no}) telah disetujui donor.",
            'data' => $disbursement,
        ]);
    }

    /**
     * Record Funds Received with Actual FX Rate
     */
    public function receiveFunds(Request $request, $id)
    {
        $disbursement = GrantDisbursementRequest::findOrFail($id);

        $validated = $request->validate([
            'received_date' => 'required|date',
            'received_amount' => 'required|numeric|min:0.01',
            'actual_fx_rate' => 'required|numeric|min:0.0001',
            'received_bank_account_id' => 'required|exists:bank_accounts,id',
            'receipt_proof' => 'nullable|file|max:10240',
            'received_notes' => 'nullable|string',
        ]);

        $receivedAmount = (float) $validated['received_amount'];
        $actualRate = (float) $validated['actual_fx_rate'];
        $contractRate = (float) ($disbursement->contract_fx_rate ?: 1);

        // IDR value calculated at actual exchange rate
        $receivedAmountIdr = round($receivedAmount * $actualRate, 2);

        // Foreign Exchange Gain or Loss calculation:
        // (Actual Rate - Contract Rate) * Received Foreign Amount
        $fxGainLoss = round(($actualRate - $contractRate) * $receivedAmount, 2);

        $receiptPath = $disbursement->receipt_proof_path;
        if ($request->hasFile('receipt_proof')) {
            if ($receiptPath && Storage::disk('public')->exists($receiptPath)) {
                Storage::disk('public')->delete($receiptPath);
            }
            $receiptPath = $request->file('receipt_proof')->store('disbursement-receipts', 'public');
        }

        $disbursement->update([
            'status' => 'received',
            'received_date' => $validated['received_date'],
            'received_amount' => $receivedAmount,
            'actual_fx_rate' => $actualRate,
            'received_amount_idr' => $receivedAmountIdr,
            'fx_gain_loss' => $fxGainLoss,
            'received_bank_account_id' => $validated['received_bank_account_id'],
            'receipt_proof_path' => $receiptPath,
            'received_notes' => $validated['received_notes'] ?? null,
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Penerimaan dana berhasil dicatat dengan kurs realisasi (Actual FX Rate).',
            'data' => $disbursement->load(['grantAgreement.donor', 'currency', 'bankAccount', 'receivedBankAccount']),
            'calculation' => [
                'received_amount' => $receivedAmount,
                'actual_fx_rate' => $actualRate,
                'contract_fx_rate' => $contractRate,
                'received_amount_idr' => $receivedAmountIdr,
                'fx_gain_loss' => $fxGainLoss,
                'is_fx_gain' => $fxGainLoss >= 0,
            ],
        ]);
    }

    /**
     * Delete disbursement request
     */
    public function destroy($id)
    {
        $disbursement = GrantDisbursementRequest::findOrFail($id);

        if ($disbursement->status === 'received') {
            return response()->json([
                'success' => false,
                'message' => 'Permintaan yang sudah diterima dananya tidak dapat dihapus.',
            ], 422);
        }

        $disbursement->delete();

        return response()->json([
            'success' => true,
            'message' => 'Disbursement Request berhasil dihapus.',
        ]);
    }
}

