# ECO FINANCE KAOEM TELAPAK

## Daftar Fitur, SOP Penggunaan, dan Alur Bisnis

Dokumen ini menjelaskan fitur yang tersedia pada aplikasi Eco Finance Kaoem Telapak, cara penggunaannya, hubungan antar-modul, serta status verifikasi implementasi. Dokumen ini dapat digunakan sebagai bahan demo, handover, dan pembahasan ruang lingkup dengan client.

> Catatan: status “terverifikasi” pada dokumen ini merujuk pada pengujian source code, API, database, dan automated test yang tersedia. Uji browser Web–PWA pada environment staging dan integrasi eksternal e-Bupot/e-Faktur masih memerlukan UAT dengan akun dan environment client.

---

## 1. Aturan Umum Aplikasi

### 1.1 Fiscal Year

Fiscal Year adalah konteks periode aktif yang digunakan untuk memisahkan data 2025, 2026, dan 2027.

Cara menggunakan:

1. Pilih Fiscal Year dari dropdown pada navbar.
2. `Selected` berarti tahun yang sedang dilihat atau digunakan sebagai filter.
3. `System active` berarti tahun default aplikasi.
4. `Open` berarti periode masih dapat menerima transaksi.
5. `Closed` berarti periode tidak boleh menerima posting baru.

Satu tahun dapat menjadi `System active`, sedangkan beberapa tahun dapat tetap berstatus `Open` untuk kebutuhan administrasi dan histori.

### 1.2 Hak Akses

Hak akses dikontrol melalui Role dan Permission. Contoh peran:

- Staff: membuat expense, timesheet, dan permintaan transaksi.
- Supervisor/Approver: melakukan pemeriksaan dan persetujuan.
- Finance: verifikasi, posting, pembayaran, dan rekonsiliasi.
- Project Manager: mengelola project, workplan, dan budget project.
- Tax/Accounting Officer: pajak, jurnal, GL, dan laporan.
- Administrator: master data, user, role, workflow, dan konfigurasi.

### 1.3 Pola Status Transaksi

Sebagian besar transaksi mengikuti pola:

`Draft → Submitted → Verified → Approved → Posted → Paid/Completed`

Transaksi dapat ditolak, dikembalikan untuk diperbaiki, dibatalkan, atau dibalik melalui reversal sesuai kewenangan.

---

## 2. Dashboard

### Global Dashboard

Fungsi:

- Melihat ringkasan kas, grant, expense, approval, budget, dan performa fund.
- Memfilter Fiscal Year, Project, Donor, tanggal, dan bank account.
- Melihat Budget vs Expenses dan Fund Allocation.
- Mengakses Bank Reconciliation dari ringkasan kas.

Cara menggunakan:

1. Pilih Fiscal Year.
2. Atur filter Project, Donor, dan tanggal jika diperlukan.
3. Pilih bank account untuk melihat saldo kas tertentu.
4. Klik kartu atau link detail untuk membuka modul sumber.

Sumber angka keuangan berasal dari transaksi yang sudah diposting ke accounting, bukan hanya dari input draft.

---

## 3. Master Data

Master Data adalah sumber referensi bersama. Data yang dipilih pada form transaksi berasal dari master, bukan pilihan hardcoded.

### 3.1 Finance & Accounting

#### Bank Accounts

Menyimpan rekening bank organisasi, mata uang, organisasi, dan akun GL.

SOP:

1. Tambah bank name, account number, account holder, currency, organization, dan GL account.
2. Isi Fiscal Year jika rekening bersifat period-specific.
3. Simpan sebagai active agar muncul pada dropdown transaksi.
4. Nonaktifkan rekening yang tidak lagi digunakan; jangan menghapus rekening yang sudah memiliki transaksi.

#### Payment Methods

Menyimpan metode pembayaran seperti bank transfer, cash, atau e-wallet dan akun GL default.

#### Currencies

Menyimpan kode, nama, simbol, decimal places, base currency, dan status active.

#### FX Rates

Menyimpan nilai tukar berdasarkan tanggal, sumber kurs, dan pasangan mata uang.

#### Fiscal Years

Menyimpan tahun fiskal, nama periode, tanggal mulai, tanggal selesai, system active, dan status periode.

#### Accounting Periods

Menyimpan periode bulanan atau periodik di bawah Fiscal Year. Periode dapat ditutup atau dibuka oleh role yang berwenang.

#### Tax Master

Menyimpan tax code, jenis pajak, tarif, tanggal berlaku, akun penjualan/pembelian, serta status aktif.

#### Customers

Menyimpan customer untuk kebutuhan Accounts Receivable dan penerimaan pembayaran.

### 3.2 Programs & Projects

#### Programs

Menyimpan program induk berdasarkan Fiscal Year, tujuan, PIC, tanggal mulai/selesai, dan status.

#### Funding Sources

Menyimpan sumber pendanaan yang dipakai oleh grant, budget, project, expense, dan reporting.

#### Donor Types

Menyimpan klasifikasi donor seperti government, foundation, NGO, atau kategori internal lainnya.

### 3.3 Budget & Reporting

#### Budget Categories

Menyimpan kategori anggaran seperti Personnel & Consultant Fees, Direct Activity & Workshop Costs, serta Travel & Field Accommodation.

#### Budget Categories & Templates / Budget Lines

Menyimpan template dan line budget dengan kategori, project, Fiscal Year, nominal, dan deskripsi.

#### Reporting Categories

Menyimpan klasifikasi untuk kebutuhan laporan.

#### Reporting Dimensions

Menyimpan dimensi analisis seperti project, donor, program, department, fund, dan kategori pelaporan.

### 3.4 Expenses & Assets

#### Expense Categories

Menyimpan kategori expense dan akun GL default.

#### Document Types

Menyimpan tipe dokumen pendukung expense.

#### Asset Categories

Menyimpan kategori fixed asset dan mapping akuntansinya.

### 3.5 Dropdown dan Relasi Master Data

Dropdown mengikuti hubungan berikut:

`Donor → Grant Agreement → Program/Project → Workplan → Activity → Budget Line`

Relasi lain yang digunakan:

`Bank Account → Currency → Organization → GL Account`

`Vendor → Vendor Category → Item/Service → Unit of Measure → Tax Code`

Jika parent berubah, child yang tidak sesuai harus di-reset. Backend juga memvalidasi foreign key agar kombinasi yang tidak sesuai tidak dapat disimpan.

---

## 4. Donor & Grant

### Integrated Donor & Grant

Satu form terintegrasi untuk membuat:

- Donor
- Grant Agreement
- Project
- Project Framework/Logframe
- Workplan
- Budget Lines
- Dokumen pendukung

Cara menggunakan:

1. Isi kode, nama, tipe, dan informasi kontak donor.
2. Isi nomor grant, nilai grant, tanggal, mata uang, funding source, bank account, dan status.
3. Hubungkan grant dengan project.
4. Tambahkan project framework dan indikator.
5. Tambahkan workplan dan aktivitas.
6. Tambahkan budget line dan kategori anggaran.
7. Upload agreement, proposal, workplan, atau detailed budget bila tersedia.
8. Simpan satu record terintegrasi.

Hasil:

- Donor menjadi parent.
- Grant Agreement terhubung ke donor.
- Project terhubung ke grant dan donor.
- Framework, workplan, budget, dan dokumen dapat ditelusuri dari record yang sama.

### Project Frameworks

Digunakan untuk mengelola impact, outcome, output, activity, indicator, baseline, dan target.

SOP:

1. Pilih donor.
2. Pilih Grant Agreement milik donor tersebut.
3. Pilih atau buat project.
4. Tambahkan level framework, code, description, indicator, baseline, dan target.
5. Simpan atau import data framework.

### Project Timeline / Workplan

Digunakan untuk mengelola aktivitas, output, tanggal mulai/selesai, status, baseline, progress, dan target penutupan.

### Grant / Project Budget

Digunakan untuk menyusun budget berdasarkan project, grant, Fiscal Year, funding source, budget category, dan budget line.

### Budget Monitoring

Membandingkan approved budget, committed amount, actual spending, available budget, dan variance.

### Grant Reporting

Menyusun dan memonitor laporan grant, deadline laporan, status submission, dan kesiapan data pendukung.

---

## 5. Expenses & Approvals

### Expense Requests

Jenis transaksi:

- Expense Request
- Reimbursement
- Cash Advance
- Settlement

Cara menggunakan expense request:

1. Pilih Fiscal Year.
2. Pilih project, donor/grant, activity, budget line, expense category, dan payment method.
3. Isi tanggal, uraian, nominal, dan pajak jika relevan.
4. Upload bukti transaksi.
5. Simpan draft.
6. Submit untuk verifikasi.

Alur:

`Draft → Submit → Finance Verification → Approval → Budget Control → Posting → Payment`

### Reimbursements

Untuk penggantian biaya yang sudah dikeluarkan oleh staff.

SOP:

1. Isi employee, tanggal, aktivitas, project, budget line, nominal, dan bukti.
2. Submit.
3. Finance melakukan verifikasi.
4. Approver menyetujui atau menolak.
5. Finance melakukan posting dan payment.
6. Payment masuk ke payment register, bank, dan GL.

### Cash Advances

Untuk uang muka kegiatan.

SOP:

1. Buat cash advance dengan staff, aktivitas, project, grant, budget line, dan nominal.
2. Submit dan minta approval.
3. Finance membayar advance.
4. Staff membuat settlement setelah kegiatan selesai.
5. Sistem menghitung pemakaian, sisa uang muka, atau pengembalian.

### Approval Center

Satu antrian approval untuk expense, settlement, payment, tax, journal, AP, AR, timesheet, asset, dan bank reconciliation sesuai permission.

### Finance Verification

Memvalidasi kelengkapan dokumen, budget, account mapping, tax, dan dimensi pendanaan sebelum approval/posting.

### Payment Processing

Menangani pembayaran transaksi yang sudah approved dan memenuhi syarat posting.

### Expense Monitoring

Memantau status, outstanding, paid amount, budget utilization, dan transaksi yang memerlukan tindakan.

---

## 6. Accounting

### Chart of Accounts

Menyimpan struktur akun, parent account, kategori asset/liability/equity/revenue/expense, dan status aktif.

### Journal

Digunakan untuk manual journal, recurring journal, adjustment, reversal, serta journal yang berasal dari modul transaksi.

Validasi wajib:

- Total debit sama dengan total credit.
- Fiscal Year dan accounting period terbuka.
- Account aktif.
- Dimensi project, donor, grant, fund, dan department valid.
- Source transaction dan source ID tersimpan.

### General Ledger

Menampilkan seluruh jurnal posted berdasarkan account, tanggal, Fiscal Year, project, donor, grant, dan dimensi lainnya.

### Accounts Payable

Mengelola supplier invoice, payable, posting AP, payment, dan status settlement.

### Accounts Receivable

Mengelola customer invoice, receivable, posting AR, dan penerimaan pembayaran.

### Banking

Mengelola bank transaction, import mutasi, matching, unmatched transaction, dan posting bank.

### Bank Reconciliation

SOP:

1. Import atau input mutasi bank.
2. Cari transaksi yang sesuai.
3. Lakukan auto-match atau manual match.
4. Tandai sebagai reconciled.
5. Tinjau exception sebelum period closing.

### Fixed Assets

Mengelola asset register, capitalization, depreciation, impairment, transfer, stock opname, dan disposal.

### Period Closing

Menutup periode akuntansi setelah seluruh posting, rekonsiliasi, dan review selesai. Periode tertutup menolak posting tidak sah.

### Posting Accounting

Sumber posting utama:

| Sumber | Event | Hasil |
|---|---|---|
| Expense | Approved/post/pay | Accrual/payment journal, bank, tax bila ada |
| Reimbursement | Approved/pay | Payable dan payment journal |
| Cash Advance | Pay/settle | Advance, settlement, return atau expense journal |
| Procurement | Supplier invoice/post/pay | AP, payment, bank, GL |
| Timesheet | Approved/post labor cost | Labor cost journal dan GL |
| Tax | Tax transaction/post | Tax payable/receivable dan tax report |
| Bank | Match/reconcile | Bank transaction dan reconciliation status |
| Fixed Asset | Capitalize/depreciate/dispose | Asset journal dan GL |

Posting dibuat idempotent untuk mencegah jurnal dan pembayaran ganda.

---

## 7. Procurement

### Procurement Dashboard

Menampilkan status PR, RFQ, CBA, PO, receipt, invoice, contract, dan exception.

### Purchase Request

SOP:

1. Buat PR dengan project, grant, budget line, item/service, quantity, UOM, harga, vendor bila diketahui, dan justification.
2. Submit untuk approval.
3. Sistem melakukan budget check.

### RFQ & CBA

Mengirim atau mencatat quotation vendor, membandingkan harga/terms, lalu membuat Comparative Bid Analysis.

### Vendors

Mengelola supplier, category, tax code, rekening supplier, contact, dan status.

### Purchase Orders

Membuat PO dari PR/RFQ/CBA, mengelola approval, amendment, dan cancellation.

### Procurement Contracts

Mengelola kontrak supplier, periode, nilai, status active/terminated, dan dokumen.

### Supplier Contract Notification

Mencatat notifikasi kontrak dan menerbitkan atau membatalkannya sesuai workflow.

### Goods Receipts

Mencatat barang/jasa yang diterima, quantity, tanggal, dan partial receipt.

### Supplier Invoices

Mencatat invoice supplier dan menjalankan three-way match:

`Purchase Order ↔ Goods Receipt ↔ Supplier Invoice`

Invoice yang match dapat diteruskan ke AP dan payment.

### Waiver & Justification

Menyimpan alasan pengecualian procurement, approval, dan dokumen pendukung.

Alur lengkap procurement:

`PR → Approval → RFQ → Quotation → CBA → PO → Contract → Goods Receipt → Supplier Invoice → Three-way Match → AP → Payment → GL`

---

## 8. Taxes

### Tax Setup

Mengatur tax code, rate, category, effective date, exemption, dan account mapping.

### Tax Transactions

Menghitung dan menyimpan taxable amount, tax amount, tax code, source transaction, Fiscal Year, dan snapshot perhitungan.

### PPh dan VAT/PPN

Digunakan untuk pencatatan dan pelaporan pajak sesuai tax master yang aktif. Tarif tidak boleh diasumsikan di frontend; backend menjadi sumber perhitungan utama.

### e-Bupot dan e-Faktur

Halaman dan struktur data tersedia untuk pengelolaan dokumen/pelaporan. Integrasi langsung ke layanan eksternal DJP memerlukan konfigurasi dan UAT client.

### Tax Calendar

Memonitor deadline pelaporan dan kewajiban pajak.

### Tax Reports

Menyajikan transaksi pajak berdasarkan periode, tax code, jenis pajak, status, dan sumber transaksi.

### Tax Calculator

Cara menggunakan:

1. Pilih jenis pajak.
2. Isi tanggal transaksi.
3. Isi taxable amount.
4. Pilih tax code yang aktif.
5. Sistem mengambil rate berdasarkan effective date.
6. Tinjau hasil dan simpan jika diperlukan.

Perhitungan transaksi nyata dan calculator menggunakan backend tax calculation service yang sama.

---

## 9. Timesheet

### Dashboard

Melihat total jam, billable/non-billable, status approval, project allocation, dan progress.

### Internal Timesheet / My Timesheet

SOP:

1. Pilih employee, Fiscal Year, tanggal, project, donor/grant, workplan, activity, dan tipe jam.
2. Isi jumlah jam dan catatan.
3. Sistem memvalidasi project assignment, range jam, duplikasi, dan periode.
4. Simpan lalu submit.

### Team Timesheet

Supervisor melihat timesheet anggota tim dan melakukan review.

### Approval

Approver menyetujui atau menolak timesheet.

### External Timesheet

Untuk consultant atau external worker, termasuk pembuatan external invoice bila workflow mengizinkan.

### Verification dan Project Timesheet

Finance/HR memverifikasi jam, rate, assignment, dan alokasi biaya ke project/grant.

### Timesheet Reports

Menampilkan jam, biaya tenaga kerja, project, donor/grant, Fiscal Year, dan status posting.

Alur lengkap:

`Employee → Project Assignment → Timesheet → Supervisor Approval → Verification → Labor Cost Allocation → Journal → GL → Reports`

---

## 10. Reports

### Financial Reports

Menyajikan Balance Sheet/Statement of Financial Position, income/expense, trial balance, GL, AP/AR, cash, dan bank.

### Budget Reports

Menyajikan approved budget, commitment, actual, available, variance, dan utilization.

### Donor / Grant Reports

Menyajikan grant portfolio, donor allocation, utilization, grant agreement, dan reporting readiness.

### Project Reports

Menyajikan project budget, workplan, framework, activity, progress, dan actual.

### Procurement Reports

Menyajikan PR, RFQ, quotation, CBA, PO, receipt, invoice, contract, dan procurement cycle status.

### Tax Reports

Menyajikan tax transaction, payable/receivable, PPh, VAT/PPN, calendar, dan status reporting.

### Management Reports

Menyajikan ringkasan lintas modul untuk manajemen.

Filter yang harus digunakan sesuai kebutuhan:

- Fiscal Year
- Date Range
- Donor
- Grant
- Program
- Project
- Department
- Account
- Bank Account

Angka actual finance harus berasal dari posted journal dan dapat ditelusuri kembali ke source transaction.

---

## 11. Administration

### Staff

Mengelola data staff/employee, user mapping, department, position, office, worker type, dan status aktif.

### Organization Structure

Mengelola organization, department, office location, dan struktur unit kerja.

### Organization, Department, Office Locations, Positions

Menjadi referensi untuk employee, project assignment, approval workflow, timesheet, expense, dan reporting dimensions.

### Document Repository

Menyimpan dokumen organisasi, agreement, kontrak, dan dokumen pendukung.

### Internal / Legal Contracts

Mengelola kontrak internal, legal contract, tanggal berlaku, status, dan dokumen.

### Audit Log

Mencatat user, waktu, aksi, source record, perubahan data, dan event penting untuk audit trail.

---

## 12. Settings

### Users

Membuat user, mengatur employee mapping, email, worker type, dan status aktif.

### Roles & Permissions

Mengatur akses menu dan aksi create, view, update, delete, submit, approve, post, pay, dan export.

### Approval Workflow

Mengatur urutan approver berdasarkan module, amount, organization, project, atau aturan lainnya.

### System Settings

Mengatur company profile, base currency, tax policy, approval policy, dan pengaturan sistem.

### Integrations

Mengelola status koneksi dan konfigurasi integrasi.

### Notifications

Menyediakan notifikasi approval, reminder, exception, dan aktivitas penting.

---

## 13. Alur End-to-End Utama

### 13.1 Donor sampai Report

`Master Data → Donor → Funding Source → Grant Agreement → Project → Framework → Workplan → Budget → Expense/Procurement/Timesheet → Approval → Accounting → Payment → GL → Reports`

### 13.2 Expense sampai GL

`Expense Request → Submit → Verify → Approve → Budget Control → Journal/Accrual → Pay → Bank → GL → Report`

### 13.3 Procurement sampai GL

`PR → RFQ/CBA → PO → Receipt → Supplier Invoice → Three-way Match → AP → Payment → Bank → GL → Report`

### 13.4 Timesheet sampai GL

`Timesheet → Supervisor Approval → Finance Verification → Labor Allocation → Journal → GL → Project/Donor Report`

### 13.5 Tax sampai Report

`Transaction → Tax Code → Tax Calculation → Tax Record → Tax Journal → Tax Payable/Receivable → Tax Report`

---

## 14. Status Verifikasi untuk Client

### Sudah diverifikasi

- Backend migration dan seeder pada environment isolated.
- Backend PHPUnit: 82 tests, 672 assertions.
- Dropdown contract dan dependent relation test.
- Donor → Grant → Project → Activity → Budget Line chain.
- Fiscal Year propagation pada jalur transaksi yang diuji.
- Accounting debit-credit balance dan source trace.
- Posting idempotency dan duplicate prevention pada skenario yang diuji.
- Frontend test: 9 files, 63 tests.
- Frontend production build.
- PWA build dan test.

### Masih membutuhkan UAT client

- Browser E2E Web ↔ Mobile PWA dengan akun staging Staff, Supervisor, Finance, dan Approver.
- Pengujian perangkat mobile nyata dan sinkronisasi status.
- Integrasi eksternal e-Bupot/e-Faktur.
- Konfirmasi versi MySQL production, timezone, collation, decimal, dan transaction setting.
- UAT final untuk approval matrix, data historis, dan format laporan client.

## 15. Pernyataan Klaim yang Aman

Gunakan pernyataan berikut saat menyampaikan ke client:

> Eco Finance Kaoem Telapak telah memiliki modul Master Data, Donor & Grant, Project, Framework, Workplan, Budget, Expenses & Approvals, Procurement, Accounting, Taxes, Timesheet, Reports, Administration, dan Settings. Modul-modul tersebut telah dihubungkan melalui relasi database, Fiscal Year context, workflow approval, posting accounting, General Ledger, dan reporting. Backend, migration, seeder, automated integration test, frontend test, dan PWA build telah diverifikasi pada environment isolated. Final go-live tetap memerlukan UAT browser Web–PWA, validasi production database, serta pengujian integrasi eksternal pajak.

Jangan menyatakan “100% production ready” sebelum seluruh UAT tersebut selesai.

