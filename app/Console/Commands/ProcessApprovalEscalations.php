<?php

namespace App\Console\Commands;

use App\Models\Expense\ExpenseRequest;
use App\Models\Procurement\PurchaseRequest;
use App\Models\Accounting\Journal;
use App\Models\Timesheet\TimesheetEntry;
use App\Models\User;
use App\Notifications\ApprovalReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ProcessApprovalEscalations extends Command
{
    protected $signature = 'app:process-approval-escalations {--days=3 : Days threshold for overdue}';

    protected $description = 'Send email reminders to approvers for pending items older than N days';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = Carbon::now()->subDays($days);

        $this->info("Processing escalations for items pending > {$days} days (before {$cutoff->toDateString()})...");

        // Gather all overdue pending items
        $overdueItems = collect();

        // Overdue Expense Requests
        ExpenseRequest::query()
            ->where('status', 'submitted')
            ->where('updated_at', '<', $cutoff)
            ->with(['requester', 'project', 'department'])
            ->get()
            ->each(function (ExpenseRequest $exp) use ($overdueItems) {
                $overdueItems->push([
                    'module' => 'expense',
                    'module_label' => 'Expense Request',
                    'reference_number' => $exp->request_number ?? 'EXP-' . $exp->id,
                    'title_summary' => $exp->description ?? 'Pengajuan expense',
                    'amount' => (float) $exp->total_amount,
                    'created_at' => $exp->created_at?->toISOString(),
                    'permission_required' => 'expense.approve',
                ]);
            });

        // Overdue Purchase Requests
        PurchaseRequest::query()
            ->whereIn('status', ['submitted', 'pending_approval'])
            ->where('updated_at', '<', $cutoff)
            ->get()
            ->each(function (PurchaseRequest $pr) use ($overdueItems) {
                $overdueItems->push([
                    'module' => 'pr',
                    'module_label' => 'Purchase Request',
                    'reference_number' => $pr->pr_number ?? 'PR-' . $pr->id,
                    'title_summary' => $pr->description ?? 'Pengajuan PR',
                    'amount' => (float) $pr->total_amount,
                    'created_at' => $pr->created_at?->toISOString(),
                    'permission_required' => 'procurement.pr.approve',
                ]);
            });

        // Overdue Journals
        Journal::query()
            ->whereIn('status', ['submitted', 'reviewed'])
            ->where('updated_at', '<', $cutoff)
            ->get()
            ->each(function (Journal $journal) use ($overdueItems) {
                $overdueItems->push([
                    'module' => 'journal',
                    'module_label' => 'Journal GL',
                    'reference_number' => $journal->journal_number,
                    'title_summary' => $journal->description ?? 'Jurnal Umum',
                    'amount' => (float) $journal->lines()->sum('debit'),
                    'created_at' => $journal->created_at?->toISOString(),
                    'permission_required' => 'accounting.journal.post',
                ]);
            });

        // Overdue Timesheets
        TimesheetEntry::query()
            ->where('status', 'submitted')
            ->where('updated_at', '<', $cutoff)
            ->with('employee')
            ->get()
            ->each(function (TimesheetEntry $ts) use ($overdueItems) {
                $overdueItems->push([
                    'module' => 'timesheet',
                    'module_label' => 'Timesheet',
                    'reference_number' => 'TS-' . $ts->id,
                    'title_summary' => $ts->task_description . ' (' . $ts->hours_spent . ' jam)',
                    'amount' => (float) $ts->hours_spent,
                    'created_at' => $ts->created_at?->toISOString(),
                    'permission_required' => 'timesheet.approve',
                ]);
            });

        if ($overdueItems->isEmpty()) {
            $this->info('No overdue pending items found. No reminders sent.');
            return self::SUCCESS;
        }

        $this->info("Found {$overdueItems->count()} overdue items. Sending notifications...");

        // Group by required permission and send to eligible approvers
        $byPermission = $overdueItems->groupBy('permission_required');
        $notifiedCount = 0;

        foreach ($byPermission as $permission => $items) {
            $approvers = User::query()
                ->whereHas('role.permissions', fn ($q) => $q->where('slug', $permission))
                ->get();

            foreach ($approvers as $approver) {
                \App\Models\Notification::create([
                    'user_id' => $approver->id,
                    'title' => 'Reminder persetujuan',
                    'message' => "Terdapat " . count($items) . " pengajuan yang menunggu persetujuan Anda lebih dari {$days} hari.",
                    'type' => 'approval',
                    'action_url' => '/approvals',
                ]);

                $approver->notify(new ApprovalReminderNotification(
                    pendingItems: $items->all(),
                    overdueDays: $days,
                ));
                $notifiedCount++;
            }
        }

        $this->info("Sent {$notifiedCount} reminder notifications successfully.");

        return self::SUCCESS;
    }
}
