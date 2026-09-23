# Laporan Audit & Perbaikan Backend ERP Kaoem Telapak

Tanggal: 23 September 2026
Status: Batch 1–9 selesai, seluruh suite pengujian hijau (**36 test / 241 assertion PASS**).

---

## Audit Awal

Laravel 11 backend ERP di-review menyeluruh (RBAC, master data, procurement, expense,
timesheet, accounting/journal, AP/AR, tax, fixed asset, budget, banking, report, audit trail).
Temuan utama yang diperbaiki:

**Kontrol posting & integritas data transaksional (tingkat keparahan tinggi)**
- Timesheet dapat *double-post* ke GL tanpa penjaga; posting lintas periode fiskal/GL tidak
  diblokir; seorang user bisa memosting entry milik user lain (kurang scope silang).
- Lanjutnya posting expense/cash-advance bisa berjalan tanpa pengecekan periode buka
  (fiscal-period), sehingga transaksi bisa masuk ke periode terkunci/closed.
- Commitment budget (commit/release/convert) tidak dikelola konsisten antara PR, expense,
  dan AP/AR → anggaran bisa over-commit / komitmen menggantung tak pernah dilepas.
- Akses data tidak discope pada sejumlah list/query (journal, AP reconciliation, tax,
  procurement, budget monitoring, fixed asset) → B2B/B4B page-data bocor antar user.
- Tidak ada audit trail untuk aksi login/logout, posting, dan perubahan master.

**RBAC & approval**
- ApprovalMatrix approver-role (*executive-director*, *board-director*, *project-manager*,
  *finance-officer*) tidak punya permission di `DatabaseSeeder` → approver tak dapat
  melihat item persetujuan.
- `ProcessApprovalEscalations` menggunakan relasi `roles` yang tak ada pada `User` + lookup
  permission memakai kolom `name` padahal diverifikasi dengan `slug`, dan channel `database`
  yang tidak kompatibel dengan tabel `notifications` kustom.

---

## Implementasi

Dikerjakan dalam 9 batch, masing-masing ditutup dengan run test.

### Batch 1 — Timesheet posting (Kontrol posting GL)
- Guard double-post (status `posted`/`journal_id` sudah ada → tolak) sebelum memproses.
- Penjaga periode: posting hanya diizinkan pada fiscal period yang terbuka.
- Blok lintas user (scoping): `authorizeScope` membatasi `created_by`/project saat post.
- Set `posted_by`/`posted_at`, `journal_id`, dan tulis audit trail.
- `store()` memblokir pembuatan entry untuk employee pihak lain kecuali permission
  approver/jalur resmi.

### Batch 2 — Purchasing : validasi budget PR sebelum approve
- Validasi anggaran dipindah **sebelum** memasuki workflow approve (bukan setelah).
- Commitments dihitung **agregat per budget_line** (menjaga batas garis anggaran), tidak
  per-request.

### Batch 3 — Lifecycle commitment (commit → release → convert)
- `BudgetMonitoringService` diberi `commit()/release()/releaseForSource()/convert()`.
- **Expense**: approve → commit; reject → release (`released`); post → convert
  (release commitment + post actual).
- **AP invoice**: saat post, meng-convert commitment PR terkait (modul `converted`).
- **AR revenue**: tidak lagi mengikat `budget_line_id` (menghilangkan distorsi actual negatif).

### Batch 4 — Journal (scope + penjaga periode fiscal-aware)
- `AccountingPeriodService::ensureOpen` kini juga mengecek fiscal year tertutup (bukan hanya
  month-end), mencegah transaksi masuk ke tahun fiskal terkunci.
- `JournalController` (index/show/reverse/transition/update/destroy) diberi data scope
  (`DataScopeService`) + override permission.

### Batch 5 — AP reconciliation, AR, tax (scope + guard)
- AP `reconcileBankTransaction`/`unmatchBankTransaction`: di-scope ke `created_by`
  (via `whereHas('payment')`) + `AuditLog` module `banking`.
- Tax: index/exportDjp/report di-scope; `store` diguard oleh `ensureOpen(transaction_date)`.

### Batch 6 — Scope procurement & project assignment
- List scope untuk GRN, Supplier Invoice, Purchase Order, RFQ, SCN.
- `project_assignments` table + `ProjectAssignment` model; `DataScopeService` ditulis ulang:
  akses = owner **ATAU** assigned project **ATAU** record tanpa owner/org (shared).

### Batch 7 — Audit login
- `AuthController` login menulis `AuditLog` (`module=auth`, action `LOGIN`) dan
  `MaliciousIntent`/account-inactive terekam; logout mempersisten audit.
- `AuditTrailTrait` sebagai dasar pencatatan audit terpusat.

### Batch 8 — Seeder approver permissions + `users.employee_id`
- `DatabaseSeeder`: role approver (*executive-director*, *board-director*,
  *project-manager*, *finance-officer*, *approver*) menerima permission module
  (dashboard/funding/budget validate/expense approve/procurement PR approve/review/timesheet
  approve) sehingga bisa login ke Approval Center.
- Migration menambah `users.employee_id` (FK ke `employees`) dengan backfill berbasis
  **correlated subquery** yang portabel lintas SQLite/MySQL/Postgres (menghindari
  `UPDATE…FROM`/JOIN yang tak didukung SQlite — ini memperbaiki kegagalan *migrate* sebelumnya).
- `User` model: relasi `employee()` fallback ke email + `employee_id` fillable.

### Batch 9 — Escalation & notification
- `ProcessApprovalEscalations`: relasi singular `role.permissions` (bukan `roles`),
  pencocokan **`slug`** (bukan `name`), inisialisasi counter, dan penulisan in-app
  `Notification` + channel mail.
- `ApprovalReminderNotification.via()` hanya `['mail']` (tabel `notifications` kustom tidak
  kompatibel dengan DatabaseNotification Laravel) — mencegah QueryException.
- Penjadwalan `app:process-approval-escalations` di `routes/console.php`.

---

## Testing

Jalankan: `php artisan test`

```
Tests:    36 passed (241 assertions)
```

Cakupan batch (contoh test hijau):
- TimesheetWorkflowTest (posting + audit + scope) — 3 test
- PurchaseRequestWorkflowTest (approve → commitment, blok over-budget) — 2 test
- ExpenseWorkflowTest + AP + APBankReconcile + AR + BudgetCommitment — clean
- JournalWorkflowTest (balanced lines, scope, review/post flow) — 2 test
- TaxTransactionTest (rate master, transaction, report) — 1 test + scoping
- SecurityDataScopeTest (staff scope blocks, spoofing, security headers) — 4 test
- ProcurementWorkflow / Receipt/Invoice match (three-way) — hijau
- FixedAssetWorkflow / Timesheet / MultiDeviceLogin / ProfileSecurity / SystemSettings
- BudgetMonitoringTest (posted actuals) + ReportsDashboard + ApprovalCenterAndAttachment
- ApprovalWorkflowTest (approval matrix dua level) + TaxTransactionTest

Semua 36 test lulus; tidak ada test yang di-skip.

---

## Remaining (di luar scope audit keamanan kali ini)

1. **Email/SMTP hanya di `log`** — konfigurasi `MAIL_MAILER=log`; belum ada driver SMTP asli.
   Scheduling escalations sudah jalan (database queue + hourly) tapi email reminder perlu
   SMTP/queue worker produksi.
2. **`approval_matrices.employee_id`** masih opsional; backfill `users.employee_id` dilakukan
   via correlated subquery saat migrasi, sebaiknya diverifikasi di database produksi (MySQL)
   karena jumlah baris `users` besar.
3. **Test coverage** belum mencakup seluruh modul pembayaran advance + attachment workflow
   secara terpisah (FinancialAdvanceWorkflow, FixedAssetWorkflow sebagian besar sudah hijau —
   tinggal menambah kasus penolakan/void).
4. **Live mode** belum ada — `QUEUE_CONNECTION=database` siap, tapi worker (supervisor) belum
   didokumentasikan untuk produksi.
5. **Front-end** (dashboard/reporting frontend) belum diaudit — laporan ini fokus backend API.
