# Kaoem Telapak ERP — Pre-Visual Functional Fix Report

Tanggal: 2026-11-05 (Asia/Jakarta)  
Branch: `codex/full-e2e-audit`

## Executive Summary

Tahap functional stabilization telah memperbaiki 8 dari 9 kegagalan baseline yang dapat direproduksi tanpa melemahkan validasi approval. Backend regression berubah dari **75 passed / 9 failed / 642 assertions** menjadi **83 passed / 1 failed / 695 assertions**. Frontend dan PWA berhasil lint/build/test sesuai script repository. Sistem **NOT READY FOR VISUAL TESTING** karena masih ada blocker AR approval yang berkaitan dengan self-approval dan batch posting.

## Initial Failed Tests and Root Cause

| ID | Modul | Masalah | Root Cause | File Diubah | Hasil Perbaikan | Test Result | Status |
|---|---|---|---|---|---|---|---|
| F-01 | AR | Approval status `in_progress`, invoice tidak menjadi posted dari batch | Test menggunakan user yang sama sebagai creator dan approver; self-approval protection menolak approver role-based, sementara batch action menelan error | `AccountsReceivableController.php`; test isolation | Posting kini menyinkronkan workflow run dan response posted selalu mencerminkan approval terminal; self-approval tetap ditolak | AR suite masih 1 fail | BLOCKED |
| F-02 | Approval Center | Pending expense module null | Queue hanya mengambil `verified`, sedangkan legacy fixture/status `submitted` tidak dipetakan | `ApprovalCenterController.php` | Legacy submitted expense ditampilkan; batch memakai verify domain method sebelum approve bila belum memiliki workflow | PASS |
| F-03 | Two-level approval | Budget holder/finance tidak dikenali | Hard-coded nama budget holder dan seeded matrix global mencampuri test chain | `ApprovalWorkflowService.php`, `ApprovalWorkflowTest.php` | Role matrix menjadi sumber kebenaran; test mengisolasi matrix | PASS |
| F-04 | Cash Advance | Submit mengembalikan status null | Submit tidak membawa `quantity/frequency/unit_price` ke finance rules; receipt policy diterapkan pada pre-disbursement cash advance | `ExpenseRequestController.php`, `CashAdvanceWorkflowTest.php` | Payload internal lengkap; cash advance cukup memakai activity plan sebelum disbursement, receipt tetap diwajibkan pada settlement | PASS (4 tests, 42 assertions) |
| F-05 | Dropdown | Donor 2026 tidak ditemukan | Seeder sengaja membuat donor global (`fiscal_year_id = null`), tetapi test hanya mencari FK tahun | `DropdownContractTest.php` | Fixture menerima donor global atau donor tahun terpilih dan tetap memvalidasi parent-child chain | PASS |
| F-06 | Encryption | APP_KEY invalid | `.env.testing` berisi base64 key dengan decoded length tidak valid | `.env.testing` | APP_KEY testing valid, tidak memakai production secret | PASS |

## Backend Fixes

- Approval matrix tidak lagi memuat allowlist nama pegawai yang hard-coded.
- Approval Center mendukung legacy submitted expense secara eksplisit tanpa melewati domain method.
- Cash advance memisahkan bukti pra-disbursement (activity plan) dari bukti settlement (receipt/invoice).
- AR posting menyelaraskan workflow run setelah posting terminal.
- Tidak ada migration production yang destruktif dibuat pada tahap ini.

## Frontend Fixes

Tidak ada perubahan visual/redesign pada tahap ini. Build frontend lulus. Lint lulus dengan warning existing pada unused imports/variables, hook dependencies, duplicate keys, unreachable code, dan indikasi demo rows; item tersebut belum diubah karena perlu audit pemakaian per komponen agar tidak menghapus implementasi aktif.

## PWA Fixes

Perbaikan fiscal-year scoping sebelumnya tetap aktif pada `kaoem-telapak-pwa/src/core/network/api-client.ts`: endpoint global dikecualikan, endpoint transaksi/reporting diberi scope, explicit caller value tidak tertimpa, dan FormData tetap ditangani tanpa Content-Type manual.

## Database Changes

- Tidak ada perubahan skema baru pada tahap ini.
- Test menggunakan isolated refresh database.
- `.env.testing` menggunakan SQLite in-memory dan key testing valid.

## API Contract Fixes

- Expense submit kini mempertahankan field line yang dibutuhkan finance rules.
- Approval Center mengembalikan module expense untuk legacy submitted records.
- AR response memetakan invoice `posted` sebagai approval terminal `approved`; workflow run juga disinkronkan setelah posting.

## Cross-Module Integration Results

PASS pada test yang mencakup donor/grant/report, expense posting/payment, cash advance/settlement, procurement three-way match, AP/payment, journal/GL, fixed asset, timesheet, tax, period closing, RBAC, dan data scope. Web-to-PWA staging synchronization, browser E2E, offline conflict handling, dan DJP integration belum dapat diuji di environment ini.

## Security and RBAC Verification

Existing security/data-scope/RBAC tests tetap lulus. Self-approval protection dipertahankan; ini menjadi penyebab blocker AR test, bukan dilemahkan untuk mengejar angka PASS.

## Final Automated Test Results

| Repository | Command | Result |
|---|---|---|
| Backend | `php artisan test` | 83 passed, 1 failed, 695 assertions |
| Frontend | `npm run lint` | PASS with warnings |
| Frontend | `npm run build` | PASS |
| PWA | `npm run lint` | PASS with warnings |
| PWA | `npm run build` | PASS |
| PWA | `npm test` | PASS, 1 test |

## Remaining Issues

1. AR batch approval test masih gagal karena fixture memakai creator yang sama sebagai approver. Perlu keputusan bisnis/staging: gunakan approver berbeda (recommended), atau dokumentasikan pengecualian self-approval—yang tidak direkomendasikan.
2. Frontend/PWA lint warnings belum seluruhnya dibersihkan.
3. Browser E2E, UAT staging, concurrent posting, offline standalone mobile, dan external DJP API belum tested.

## Readiness Gate

**NOT READY FOR VISUAL TESTING**

Alasan: masih ada 1 functional backend failure pada AR approval/batch posting. Visual testing dapat dimulai setelah test tersebut diperbaiki atau fixture bisnisnya diperjelas dan regression suite kembali seluruhnya PASS. Ini bukan production-readiness approval; staging E2E, security validation, dan UAT tetap wajib.

## Commits

- `a69eefc` — PWA fiscal-year scoping
- `92a5de5` — full audit report
- Functional fixes pada tahap ini masih berada di working tree branch `codex/full-e2e-audit` dan belum dibuat Pull Request.
