<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\GrantAgreement;
use App\Models\Master\GrantReportingDeadline;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GrantReportingDeadlineController extends Controller
{
    /**
     * List all reporting deadlines with optional filtering & search
     */
    public function index(Request $request)
    {
        $today = Carbon::today()->toDateString();

        // Automatically update past-due items from upcoming/in_progress to overdue
        GrantReportingDeadline::whereIn('status', ['upcoming', 'in_progress'])
            ->where('due_date', '<', $today)
            ->update(['status' => 'overdue']);

        $query = GrantReportingDeadline::with([
            'grantAgreement:id,grant_no,agreement_no,agreement_name,donor_id,currency_id,start_date,end_date,reporting_period,status',
            'grantAgreement.donor:id,code,name,type',
            'grantAgreement.currency:id,code,name',
            'grantAgreement.projects:id,grant_agreement_id,code,name',
        ]);

        if ($request->filled('grant_agreement_id')) {
            $query->where('grant_agreement_id', $request->input('grant_agreement_id'));
        }

        if ($request->filled('status') && $request->input('status') !== 'ALL') {
            $query->where('status', strtolower($request->input('status')));
        }

        if ($request->filled('report_type') && $request->input('report_type') !== 'ALL') {
            $query->where('report_type', $request->input('report_type'));
        }

        if ($request->filled('year')) {
            $query->whereYear('due_date', $request->input('year'));
        }

        if ($request->filled('search')) {
            $search = '%' . trim($request->input('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('report_type', 'like', $search)
                  ->orWhere('period_label', 'like', $search)
                  ->orWhere('notes', 'like', $search)
                  ->orWhere('submitted_by', 'like', $search)
                  ->orWhereHas('grantAgreement', function ($gq) use ($search) {
                      $gq->where('grant_no', 'like', $search)
                         ->orWhere('agreement_name', 'like', $search)
                         ->orWhereHas('donor', function ($dq) use ($search) {
                             $dq->where('name', 'like', $search)
                                ->orWhere('code', 'like', $search);
                         });
                  });
            });
        }

        $sort = $request->input('sort', 'due_date');
        $direction = strtolower($request->input('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sort, $direction);

        if ($request->boolean('all')) {
            $records = $query->get();
            return response()->json(['success' => true, 'data' => $records]);
        }

        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
        $records = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $records->items(),
            'meta' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
            ],
        ]);
    }

    /**
     * Reporting KPI and status summary
     */
    public function summary(Request $request)
    {
        $today = Carbon::today();
        $soonThreshold = Carbon::today()->addDays(7);

        // Update overdue statuses
        GrantReportingDeadline::whereIn('status', ['upcoming', 'in_progress'])
            ->where('due_date', '<', $today->toDateString())
            ->update(['status' => 'overdue']);

        $query = GrantReportingDeadline::query();
        if ($request->filled('grant_agreement_id')) {
            $query->where('grant_agreement_id', $request->input('grant_agreement_id'));
        }
        if ($request->filled('year')) {
            $query->whereYear('due_date', $request->input('year'));
        }

        $total = (clone $query)->count();
        $submitted = (clone $query)->where('status', 'submitted')->count();
        $overdue = (clone $query)->where('status', 'overdue')->count();

        // Due soon: status is upcoming or in_progress and due within next 7 days
        $dueSoon = (clone $query)->whereIn('status', ['upcoming', 'in_progress'])
            ->whereBetween('due_date', [$today->toDateString(), $soonThreshold->toDateString()])
            ->count();

        $upcoming = (clone $query)->whereIn('status', ['upcoming', 'in_progress'])->count();

        // Type breakdown
        $financialCount = (clone $query)->where('report_type', 'like', '%Financial%')->count();
        $narrativeCount = (clone $query)->where('report_type', 'like', '%Narrative%')->count();
        $otherCount = max(0, $total - ($financialCount + $narrativeCount));

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total,
                'submitted' => $submitted,
                'overdue' => $overdue,
                'due_soon' => $dueSoon,
                'upcoming' => $upcoming,
                'types' => [
                    'financial' => $financialCount,
                    'narrative' => $narrativeCount,
                    'other' => $otherCount,
                ],
            ],
        ]);
    }

    /**
     * Schedule Matrix / Calendar view data
     */
    public function calendar(Request $request)
    {
        $year = (int) $request->input('year', date('Y'));

        $deadlines = GrantReportingDeadline::with([
            'grantAgreement:id,grant_no,agreement_name,donor_id,currency_id',
            'grantAgreement.donor:id,code,name',
        ])
        ->whereYear('due_date', $year)
        ->orderBy('due_date', 'asc')
        ->get();

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $months[$m] = [
                'month' => $m,
                'month_name' => Carbon::create($year, $m, 1)->translatedFormat('F'),
                'total' => 0,
                'submitted' => 0,
                'overdue' => 0,
                'upcoming' => 0,
                'items' => [],
            ];
        }

        foreach ($deadlines as $d) {
            $m = (int) Carbon::parse($d->due_date)->format('n');
            if (isset($months[$m])) {
                $months[$m]['total']++;
                if ($d->status === 'submitted') {
                    $months[$m]['submitted']++;
                } elseif ($d->status === 'overdue') {
                    $months[$m]['overdue']++;
                } else {
                    $months[$m]['upcoming']++;
                }
                $months[$m]['items'][] = $d;
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'year' => $year,
                'schedule' => array_values($months),
            ],
        ]);
    }

    /**
     * Store new deadline manual
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'grant_agreement_id' => 'required|exists:grant_agreements,id',
            'report_type' => 'required|string|max:100',
            'period_label' => 'nullable|string|max:100',
            'due_date' => 'required|date',
            'status' => 'nullable|in:upcoming,in_progress,submitted,overdue',
            'notes' => 'nullable|string',
            'reminder_days' => 'nullable|integer|min:0|max:90',
        ]);

        if (empty($validated['status'])) {
            $today = Carbon::today()->toDateString();
            $validated['status'] = $validated['due_date'] < $today ? 'overdue' : 'upcoming';
        }

        $deadline = GrantReportingDeadline::create($validated);
        $deadline->load(['grantAgreement.donor', 'grantAgreement.currency']);

        return response()->json(['success' => true, 'message' => 'Jadwal laporan berhasil dibuat.', 'data' => $deadline], 201);
    }

    /**
     * Update deadline
     */
    public function update(Request $request, GrantReportingDeadline $grantReportingDeadline)
    {
        $validated = $request->validate([
            'grant_agreement_id' => 'sometimes|exists:grant_agreements,id',
            'report_type' => 'sometimes|string|max:100',
            'period_label' => 'nullable|string|max:100',
            'due_date' => 'sometimes|date',
            'submitted_date' => 'nullable|date',
            'submitted_by' => 'nullable|string|max:150',
            'status' => 'sometimes|in:upcoming,in_progress,submitted,overdue',
            'notes' => 'nullable|string',
            'reminder_days' => 'nullable|integer|min:0|max:90',
        ]);

        $grantReportingDeadline->update($validated);
        $grantReportingDeadline->load(['grantAgreement.donor', 'grantAgreement.currency']);

        return response()->json(['success' => true, 'message' => 'Laporan berhasil diperbarui.', 'data' => $grantReportingDeadline]);
    }

    /**
     * Submit Report with Document Upload
     */
    public function submitReport(Request $request, GrantReportingDeadline $grantReportingDeadline)
    {
        $validated = $request->validate([
            'submitted_date' => 'nullable|date',
            'submitted_by' => 'nullable|string|max:150',
            'notes' => 'nullable|string',
            'document' => 'nullable|file|max:40960|mimes:pdf,doc,docx,xls,xlsx,csv,zip,rar,jpg,jpeg,png',
        ]);

        $updateData = [
            'status' => 'submitted',
            'submitted_date' => $validated['submitted_date'] ?? Carbon::today()->toDateString(),
            'submitted_by' => $validated['submitted_by'] ?? ($request->user() ? $request->user()->name : 'Admin'),
        ];

        if (isset($validated['notes'])) {
            $updateData['notes'] = $validated['notes'];
        }

        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $path = $file->store('donor-grant-reports', 'public');
            $updateData['document_path'] = $path;
            $updateData['document_name'] = $file->getClientOriginalName();
        }

        $grantReportingDeadline->update($updateData);
        $grantReportingDeadline->load(['grantAgreement.donor', 'grantAgreement.currency']);

        return response()->json([
            'success' => true,
            'message' => 'Laporan berhasil disubmit.',
            'data' => $grantReportingDeadline,
        ]);
    }

    /**
     * Auto-generate Reporting Schedule for a Grant Agreement
     */
    public function generateSchedule(Request $request)
    {
        $validated = $request->validate([
            'grant_agreement_id' => 'required|exists:grant_agreements,id',
            'frequency' => 'required|in:quarterly,semi-annual,annual',
            'report_types' => 'required|array|min:1',
            'report_types.*' => 'string|max:100',
            'lead_time_days' => 'nullable|integer|min:0|max:60', // Hari setelah akhir periode untuk due date, misal 30 hari
        ]);

        $grant = GrantAgreement::findOrFail($validated['grant_agreement_id']);
        if (!$grant->start_date || !$grant->end_date) {
            return response()->json([
                'success' => false,
                'message' => 'Grant Agreement harus memiliki Start Date dan End Date yang valid.',
            ], 422);
        }

        $startDate = Carbon::parse($grant->start_date);
        $endDate = Carbon::parse($grant->end_date);
        $leadDays = (int) ($validated['lead_time_days'] ?? 30);
        $types = $validated['report_types'];
        $frequency = $validated['frequency'];

        $created = [];
        $cursor = $startDate->copy();
        $today = Carbon::today()->toDateString();

        DB::transaction(function () use ($cursor, $endDate, $frequency, $leadDays, $types, $grant, $today, &$created) {
            $periodNum = 1;
            while ($cursor->lessThan($endDate)) {
                $periodStart = $cursor->copy();

                if ($frequency === 'quarterly') {
                    $periodEnd = $periodStart->copy()->addMonths(3)->subDay();
                    $label = 'Q' . $cursor->quarter . ' ' . $cursor->year;
                    $cursor->addMonths(3);
                } elseif ($frequency === 'semi-annual') {
                    $periodEnd = $periodStart->copy()->addMonths(6)->subDay();
                    $label = 'Semester ' . ($cursor->month <= 6 ? '1' : '2') . ' ' . $cursor->year;
                    $cursor->addMonths(6);
                } else { // annual
                    $periodEnd = $periodStart->copy()->addYear()->subDay();
                    $label = 'Annual ' . $cursor->year;
                    $cursor->addYear();
                }

                if ($periodEnd->greaterThan($endDate)) {
                    $periodEnd = $endDate->copy();
                }

                $dueDate = $periodEnd->copy()->addDays($leadDays)->toDateString();
                $status = $dueDate < $today ? 'overdue' : 'upcoming';

                foreach ($types as $type) {
                    // Check if already exists to prevent duplicate
                    $existing = GrantReportingDeadline::where('grant_agreement_id', $grant->id)
                        ->where('report_type', $type)
                        ->where('period_label', $label)
                        ->first();

                    if (!$existing) {
                        $created[] = GrantReportingDeadline::create([
                            'grant_agreement_id' => $grant->id,
                            'report_type' => $type,
                            'period_label' => $label,
                            'due_date' => $dueDate,
                            'status' => $status,
                            'notes' => "Periode: {$periodStart->format('d M Y')} - {$periodEnd->format('d M Y')}",
                            'reminder_days' => 7,
                        ]);
                    }
                }

                $periodNum++;
            }
        });

        return response()->json([
            'success' => true,
            'message' => count($created) . ' jadwal laporan berhasil dibuat secara otomatis.',
            'created_count' => count($created),
            'data' => $created,
        ]);
    }

    /**
     * Download uploaded submission report document
     */
    public function downloadDocument(Request $request)
    {
        $path = (string) $request->query('path', '');
        if ($path === '' || str_contains($path, '..') || str_contains($path, '\\')) {
            return response()->json(['message' => 'Path dokumen tidak valid.'], 404);
        }

        $disk = Storage::disk('public');
        if (!$disk->exists($path)) {
            return response()->json(['message' => 'Dokumen tidak ditemukan di penyimpanan server.'], 404);
        }

        $absolutePath = $disk->path($path);
        $filename = $request->query('filename') ?: basename($path);

        return $request->boolean('download')
            ? response()->download($absolutePath, $filename)
            : response()->file($absolutePath);
    }

    /**
     * Delete deadline
     */
    public function destroy(GrantReportingDeadline $grantReportingDeadline)
    {
        if ($grantReportingDeadline->document_path) {
            Storage::disk('public')->delete($grantReportingDeadline->document_path);
        }

        $grantReportingDeadline->delete();
        return response()->json(['success' => true, 'message' => 'Jadwal laporan berhasil dihapus.']);
    }
}
