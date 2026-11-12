# Audit integrasi Journal dan Budget Monitoring

Tanggal: 12 November 2026. Project: Bakcend-KT.

## Hasil audit

- BudgetMonitoringService sebelumnya menjumlahkan seluruh akun debit-credit, sehingga bank dapat menghapus beban. COA sebenarnya memakai enum account_type: asset, liability, equity, revenue, expense; tidak perlu menebak dari awalan kode akun.
- Actual sebelumnya tidak memfilter fiscal_year_id journal. Filter budget menggunakan OR ke project_fiscal_year sehingga budget tahun lain ikut terpilih.
- FiscalYearScopedTrait memberi default tahun aktif meskipun frontend memilih Semua Tahun. Endpoint monitoring kini melepas hanya scope tahun tersebut dan menerapkan tahun eksplisit. Soft delete dan DataScopeService tetap diterapkan.
- Controller dan dropdown sebelumnya mengecualikan seluruh AI%. Seeder BudgetAlertThresholdSeeder menjelaskan bahwa fixture sintetis memakai AI-ALERT-*. Pengecualian kini dibatasi ke pola itu, sehingga kode operasional AI lainnya tetap muncul.
- Daftar ID hasil pembatasan akses yang kosong sebelumnya diabaikan oleh service karena when([]) bernilai false. Kini whereIn dengan daftar kosong tetap diterapkan. Panel activities, logframes, dan deadline juga dibatasi ke project/grant hasil monitoring yang terlihat.
- Validasi manual mewajibkan Project dan Budget Line pada seluruh baris, termasuk bank. Sementara jalur import Draft tidak memakai pemeriksaan relasi yang sama. Keduanya kini menggunakan JournalDimensionResolver.
- Migration backfill lama dapat menimpa donor/grant/program dan memilih relasi tanpa memeriksa konflik. Data rewrite otomatis pada kedua migration terkait dihentikan; pengisian historis tersedia melalui command eksplisit.

## Perilaku akhir

Saat simpan/update/import, resolver menggunakan Budget Line untuk menemukan Project bila Project belum dikirim, lalu Grant Project untuk mengisi donor, grant, program, project dan relation_no. account_id tidak diganti. Nomor relasi baru tidak dibuat. Dimensi yang bertentangan menghasilkan 422 pada create/update; import mempertahankan kontrak laporan error per journal dan melakukan rollback journal yang salah.

Draft boleh belum lengkap. Posting akun expense mewajibkan budget, project, grant, donor dan nomor relasi yang dapat ditentukan secara konsisten. program_id mengikuti sumber Project dan boleh null bila sumbernya null. Akun nonbeban tidak diwajibkan mempunyai Budget Line. Tidak ada pemilihan otomatis Budget Line hanya berdasarkan Project karena Project dapat memiliki banyak budget.

Actual hanya berasal dari journal posted yang tidak dihapus dan akun account_type=expense yang tidak dihapus. Debit menambah dan kredit mengurangi actual. Pembayaran accrued payable yang hanya memakai akun liability dan bank tidak menambah actual lagi. Available = approved budget - actual posted - open commitment. Commitment mengikuti tahun dan dimensi Budget Line karena tabel commitment tidak mempunyai fiscal_year_id tersendiri.

fiscal_year_id kosong atau tidak dikirim berarti Semua Tahun di endpoint monitoring, termasuk ketika header tahun aktif masih dikirim frontend. Tahun eksplisit memfilter budget_lines.fiscal_year_id secara tepat dan journals.fiscal_year_id untuk actual. Budget tanpa penetapan tahun hanya tampil pada Semua Tahun. Tahun journal null tidak dimasukkan ke pilihan tahun spesifik. Tahun Project tidak dipakai untuk mengambil seluruh budget lintas tahun.

relation_no journal line yang sudah terisi bersifat otoritatif. Fallback Project/Grant/Budget hanya dipakai saat relation_no journal line null/kosong. Angka actual lama tidak diubah di database. Budget tanpa transaksi tetap muncul dengan actual nol. Group activity/sub-output dipisahkan berdasarkan relasi, tahun, project dan identitas aktivitas, sehingga nama sama tidak mencampur proyek berbeda. data dan by_line identik; totals, alerts dan group berasal dari kumpulan budget yang sama.

## File yang diubah

| File | Perubahan |
| --- | --- |
| app/Http/Controllers/Api/Accounting/JournalController.php | Resolver bersama, Draft parsial, posting guard, fiscal year payload, import tidak menduplikasi nomor lintas tahun/soft delete |
| app/Services/Accounting/JournalDimensionResolver.php | Pengisian dan validasi konsistensi dimensi |
| app/Models/Accounting/JournalLine.php | Diaudit; relasi/fillable yang diperlukan sudah tersedia, tanpa perubahan |
| app/Http/Controllers/Api/Budget/BudgetMonitoringController.php | Scope akses, pola fixture, panel terkait dan metadata/group response |
| app/Services/Budget/BudgetMonitoringService.php | Actual expense posted, filter tahun/relasi, empty scope, budget nol transaksi |
| app/Http/Controllers/Api/Master/BaseMasterController.php | Dropdown hanya mengecualikan AI-ALERT-* |
| app/Console/Commands/BackfillJournalDimensions.php | Backfill dry-run, skip konflik, kunci baris saat apply, idempotent |
| database/migrations/2026_11_11_000009_add_relation_no_to_journal_lines.php | Tetap menambah kolom/index; menghapus pengisian data otomatis tanpa audit |
| database/migrations/2026_11_11_000010_backfill_journal_line_donor_grant_relations.php | Tidak lagi menimpa data otomatis |
| tests/Feature/JournalBudgetIntegrationTest.php | Kasus integrasi aktual, accrued payment, tahun, akses, relasi, CSV, backfill dan posting |
| tests/Feature/BudgetMonitoringTest.php | Fixture memiliki pemilik sesuai pembatasan akses yang sebenarnya |
| tests/Feature/JournalWorkflowTest.php | Fixture deskripsi valid, menegaskan posting beban tanpa dimensi ditolak |
| docs/JOURNAL_BUDGET_INTEGRATION_AUDIT.md | Laporan audit dan deployment ini |

Route, Project, GrantAgreement, BudgetLine, Journal, JournalLine, FiscalYearScopedTrait, DataScopeService dan struktur COA telah diperiksa. Tidak diperlukan perubahan route atau penghapusan aturan akses.

## Verifikasi

- Pemeriksaan php -l: seluruh 11 file PHP yang diubah/ditambahkan lulus.
- Test terarah: 13 tests, 95 assertions, seluruhnya lulus.
- git diff --check lulus; Git hanya memberi pemberitahuan normalisasi CRLF pada satu migration.
- Command journals:backfill-dimensions terdaftar di artisan list.
- PHPUnit pada Windows ini mengembalikan is_readable=false walaupun fopen/require berhasil. Pengujian dijalankan melalui launcher sementara yang mengganti pemeriksaan preflight tersebut di memori dengan is_file. Tidak ada perubahan file vendor atau assertion test.

## Deployment

1. Deploy perubahan backend beserta dua service/command baru. Backup database sebelum menjalankan migration pending.
2. Jalankan php artisan migrate:status. Bila migration 000009/000010 tanggal 11 November belum pernah diterapkan, jalankan php artisan migrate --force. Tidak ada migration schema baru. Bila keduanya sudah diterapkan, jangan rollback/rerun migration untuk mengisi data.
3. Jalankan php artisan optimize:clear dan restart worker/proses backend sesuai deployment yang digunakan.
4. Jalankan php artisan journals:backfill-dimensions --dry-run. Command tanpa opsi juga dry-run. Simpan output untuk memeriksa baris ambigu. Command tidak menebak Budget Line yang belum dipilih dan tidak mengubah nominal/status journal.
5. Setelah meninjau hasil, jalankan php artisan journals:backfill-dimensions --apply. Baris konflik dilewati dan dilaporkan; exit code nonzero menunjukkan ada baris yang perlu penanganan manual. --dry-run mengalahkan --apply bila keduanya diberikan.
6. Jalankan dry-run kembali. Data yang berhasil diisi tidak diubah ulang. Lengkapi Draft yang belum punya Budget Line melalui form journal menggunakan project/budget yang benar.
7. Di staging, periksa endpoint /api/v1/budget/monitoring untuk Semua Tahun dan tahun tertentu, posted expense + bank, accrued payable payment, serta user tanpa project assignment. Frontend harus mengosongkan parameter fiscal_year_id untuk Semua Tahun dan membaca scope dari response.
8. Jalankan php artisan test pada environment deployment yang normal. Seluruh suite belum hijau; lihat hasil lengkap di bawah. Uji staging/live belum dilakukan pada sesi ini.

Tidak ada journal production yang dihapus, diduplikasi atau diubah nominalnya. Backfill apply tidak dijalankan terhadap database aplikasi dalam sesi ini.

## Hasil suite lengkap dan perbandingan baseline

Suite akhir: 99 tests, 709 assertions; 88 lulus, 9 failure, 2 error.
Baseline HEAD diuji pada salinan terpisah dengan dependency yang sama: 91 tests, 636 assertions; 11 failure, 2 error. Seluruh 9 failure dan 2 error pada versi akhir juga ada di baseline. Dua failure tambahan baseline berasal dari JournalWorkflowTest lama; fixture dan ekspektasinya sudah disesuaikan dengan kontrak validasi yang diuji pada perubahan ini. Tidak ada nama test gagal baru pada versi akhir.

| Area | Hasil yang masih gagal | Temuan |
| --- | --- | --- |
| ApprovalCenterAndAttachmentTest | 1 failure | Pending approval count tidak sesuai |
| ApprovalWorkflowTest | 1 failure | Expense belum approved saat finance verification |
| CashAdvanceWorkflowTest | 4 failure | ExpenseRequestController memanggil toDateString pada string |
| EndToEndDonorToReportTest | 1 failure | Expense belum approved saat finance verification |
| ExpenseWorkflowTest | 2 failure | Expense belum approved saat finance verification |
| BankAccountOpeningBalancePersistenceTest | 1 error | Model BankAccount tidak ditemukan pada konteks test |
| DropdownContractTest | 1 error | Model Donor tidak ditemukan pada konteks test |

Baseline memakai sumber dari git archive HEAD, bootstrap path dan dependency path diarahkan ke salinan pengujian. File project asli tidak ditukar atau di-reset. Suite lengkap belum dapat dinyatakan hijau; perubahan integrasi Journal/Budget telah lolos test terarah dan tidak menambah kegagalan suite berdasarkan perbandingan tersebut.
