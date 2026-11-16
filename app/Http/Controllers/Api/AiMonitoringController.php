<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Journal;
use App\Models\ApprovalWorkflowAction;
use App\Models\Finance\BankTransaction;
use App\Models\Master\AccountingPeriod;
use App\Models\Master\GrantDisbursementRequest;
use App\Models\Procurement\PurchaseRequest;
use App\Models\Procurement\SupplierInvoice;
use App\Services\Approval\ApprovalWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiMonitoringController extends Controller
{
    public function index(Request $request, ApprovalWorkflowService $workflow): JsonResponse
    {
        $request->validate(['fiscal_year_id' => ['nullable', 'integer', 'exists:fiscal_years,id']]);
        $user = $request->user();
        $yearId = $request->integer('fiscal_year_id') ?: null;
        $alerts = collect();

        $pendingApprovals = ApprovalWorkflowAction::query()
            ->with(['run.approvable', 'approvalMatrix'])
            ->where('status', 'pending')
            ->whereHas('run', fn ($query) => $query->where('status', 'in_progress'))
            ->latest('id')
            ->limit(100)
            ->get()
            ->filter(fn ($action) => $action->run?->approvable && $workflow->userCanApproveAction($action, $user, $action->run->approvable));

        if ($pendingApprovals->isNotEmpty()) {
            $alerts->push($this->alert('approval', 'approval-'.$user->id, 'Tindakan', '#C45B1E',
                $pendingApprovals->count().' approval menunggu Anda',
                'Periksa dokumen pada tahap aktif agar proses operasional tidak tertahan.',
                '/expenses-approvals/approvals', 100));
        }

        if ($user->hasPermission('banking.view')) {
            $count = BankTransaction::withoutGlobalScope('fiscal_year_context')
                ->when($yearId, fn ($query) => $query->where('fiscal_year_id', $yearId))
                ->where('status', 'unmatched')
                ->whereDate('transaction_date', '<=', now()->subDays(3)->toDateString())
                ->count();
            if ($count) $alerts->push($this->alert('bank', 'bank-unmatched', 'Waspada', '#D39212',
                $count.' transaksi bank belum direkonsiliasi',
                'Transaksi telah berumur lebih dari 3 hari dan belum terhubung ke payment atau journal.',
                '/accounting/bank-reconciliation', 80));
        }

        if ($user->hasPermission('accounting.journal.view')) {
            $count = Journal::withoutGlobalScope('fiscal_year_context')
                ->when($yearId, fn ($query) => $query->where('fiscal_year_id', $yearId))
                ->whereIn('status', ['submitted', 'reviewed'])
                ->where('updated_at', '<=', now()->subDays(2))
                ->count();
            if ($count) $alerts->push($this->alert('journal', 'journal-pending', 'Waspada', '#D39212',
                $count.' journal belum diposting',
                'Journal telah menunggu review atau posting lebih dari 2 hari.',
                '/accounting/journal', 75));
        }

        if ($user->hasPermission('budget.view')) {
            $count = GrantDisbursementRequest::withoutGlobalScope('fiscal_year_context')
                ->when($yearId, fn ($query) => $query->where('fiscal_year_id', $yearId))
                ->whereIn('status', ['submitted', 'approved'])
                ->where('updated_at', '<=', now()->subDays(7))
                ->count();
            if ($count) $alerts->push($this->alert('grant', 'grant-disbursement-pending', 'Waspada', '#D39212',
                $count.' pencairan grant belum diterima',
                'Permintaan pencairan telah menunggu lebih dari 7 hari; periksa grant dan rekening penerima.',
                '/donor-grant/forecast-disbursement', 70));
        }

        if ($user->hasPermission('procurement.pr.view')) {
            $prCount = PurchaseRequest::withoutGlobalScope('fiscal_year_context')
                ->when($yearId, fn ($query) => $query->where('fiscal_year_id', $yearId))
                ->where('status', 'submitted')
                ->where('updated_at', '<=', now()->subDays(2))
                ->count();
            if ($prCount) $alerts->push($this->alert('procurement', 'pr-pending', 'Waspada', '#D39212',
                $prCount.' Purchase Request tertunda',
                'PR telah menunggu persetujuan lebih dari 2 hari.',
                '/procurement/purchase-requests', 65));
        }

        if ($user->hasPermission('procurement.invoice.view') || $user->hasPermission('ap.view')) {
            $invoiceCount = SupplierInvoice::withoutGlobalScope('fiscal_year_context')
                ->when($yearId, fn ($query) => $query->where('fiscal_year_id', $yearId))
                ->whereDate('due_date', '<', now()->toDateString())
                ->whereNotIn('status', ['paid', 'cancelled'])
                ->count();
            if ($invoiceCount) $alerts->push($this->alert('payable', 'invoice-overdue', 'Kritis', '#D35400',
                $invoiceCount.' invoice supplier melewati jatuh tempo',
                'Periksa 3-way match, approval, dan status pembayaran invoice.',
                '/accounting/accounts-payable', 90));
        }

        if ($user->hasPermission('accounting.view')) {
            $periodCount = AccountingPeriod::withoutGlobalScope('fiscal_year_context')
                ->when($yearId, fn ($query) => $query->where('fiscal_year_id', $yearId))
                ->where('status', 'open')
                ->whereDate('end_date', '<', now()->toDateString())
                ->count();
            if ($periodCount) $alerts->push($this->alert('period', 'period-overdue', 'Kritis', '#D35400',
                $periodCount.' periode akuntansi lewat tanggal tutup',
                'Selesaikan journal, rekonsiliasi bank, dan pemeriksaan readiness sebelum closing.',
                '/accounting/period-closing', 85));
        }

        return response()->json([
            'success' => true,
            'message' => 'AI operational monitoring berhasil dimuat.',
            'data' => $alerts->sortByDesc('priority')->values(),
            'meta' => ['total' => $alerts->count(), 'fiscal_year_id' => $yearId],
        ]);
    }

    private function alert(string $source, string $id, string $level, string $color, string $title, string $message, string $url, int $priority): array
    {
        return [
            'id' => $id,
            'source' => $source,
            'alert_level' => ['label' => $level, 'color' => $color],
            'title' => $title,
            'message' => $message,
            'recommendation' => $message,
            'action_url' => $url,
            'priority' => $priority,
        ];
    }
}
