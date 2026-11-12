# Audit approval Journal dan Fiscal Year

Tanggal: 12 November 2026. Project backend asli Bakcend-KT.

## Status verifikasi

Perubahan backend diuji secara lokal menggunakan SQLite in-memory. Kasus live Journal CDVRUU 2611-01 dan ID database Syaifani belum dapat diverifikasi: dua percobaan read-only ke MySQL yang dikonfigurasi ditolak dengan SQLSTATE HY000/2002 (socket permission). Tidak ada assignment atau konfigurasi produksi yang diubah, dan tidak ada approval/posting/backfill aplikasi yang dijalankan.

Nama Syaifani pada fixture test hanya label data sintetis, bukan klaim ID database produksi. Perbaikan assignment live harus ditentukan dari ID sebenarnya setelah koneksi database tersedia; jangan menjalankan UPDATE berdasarkan nama saja.

## Akar masalah kode

1. matchesApprover sebelumnya menjalankan allowlist nama Budget Holder sebelum memeriksa user_id/employee_id assignment. Orang yang secara sah ditugaskan dapat ditolak karena namanya tidak tercantum.
2. History mengambil nama dari Approval Matrix saat ini, sedangkan tindakan memeriksa snapshot workflow action. Perubahan matrix setelah submit dapat membuat tampilan dan otorisasi menunjuk orang berbeda. Pemeriksaan OR antara user, employee dan role juga dapat memperluas assignment person ke seluruh role.
3. User.employee memakai scope fiscal year dan legacy email fallback. Tahun filter halaman dapat menyembunyikan employee/matrix yang sebenarnya terhubung dengan transaksi.
4. Workflow approve memakai transaksi tersendiri dan selesai sebelum transition journal memeriksa reviewed/akses. Kegagalan status atau penyimpanan journal setelah approval dapat meninggalkan workflow yang sudah berubah.
5. Fiscal year sebelumnya diterima oleh validasi payload tetapi belum dikembalikan formatJournal, tidak memvalidasi rentang tanggal, dan pembuatan journal dapat mengikuti tahun aktif global. Binding dan query juga dapat menyembunyikan transaksi saat filter halaman berbeda.
6. Migration backfill tahun historis menggunakan urutan iterasi periode sehingga periode tumpang tindih dapat dipilih tanpa bukti unik.

## Perubahan perilaku

- Tidak ada nama orang yang menjadi dasar izin. ApprovalIdentityResolver memeriksa ID user dan employee; bila keduanya ditentukan, keduanya harus sesuai. Role tidak memperluas assignment person. Employee tanpa employee_id hanya dapat diselesaikan dari satu email yang unik, terlepas dari filter tahun halaman.
- Snapshot workflow action menjadi sumber assignment person untuk tampilan dan tindakan. Matrix tetap menjadi sumber scope project/donor/department/tahun. Matrix hilang atau scope tidak cocok ditolak. Tidak ada pelepasan scope organisasi yang sudah ada.
- Untuk stage role Budget Holder tanpa assignment person, diperlukan ProjectAssignment aktif dan berlaku pada project terkait dengan role project-manager/budget-holder/budget-holder-project-manager. manager_name dan nama tampilan tidak dipakai untuk mengizinkan approval. Tidak semua Finance Manager diizinkan.
- Self-approval tetap ditolak, termasuk akun lain yang terhubung dengan employee requester/preparer yang sama. Izin posting dan akses dokumen tetap diperiksa. Override Super Admin yang sudah ada tetap tunduk pada larangan self-approval dan scope dokumen.
- Stage yang boleh ditindak harus pending, berada pada current_level, dan workflow in_progress. Stage queued/completed bukan stage aktif. Progress tidak menyembunyikan action lain yang kebetulan mempunyai level sama.
- JournalPostingGuard digunakan oleh endpoint post dan perhitungan kemampuan tindakan untuk memeriksa permission, akses, reviewed, periode terbuka, tahun/tanggal, keseimbangan nominal serta dimensi budget. Seluruh mutasi action/event/run dan penyimpanan journal berada dalam satu transaksi dengan lock journal/run.
- reviewed berarti pemeriksaan journal selesai. in_progress berarti masih menunggu tahap approval. approved adalah status workflow setelah semua tahap selesai. posted adalah status transaksi ledger. Approval tahap antara mengembalikan pesan menunggu tahap berikutnya dan journal tetap reviewed.
- List/detail/history mengembalikan status workflow, document_status, current_level/current_stage, steps dengan ID assignment, approvers yang sesuai resolver, can_approve, can_post dan alasan tindakan tidak tersedia. Future queued stages tetap tersembunyi dari pengguna biasa sesuai aturan visibility sebelumnya.
- Create/update mengembalikan fiscal_year_id dan fiscal_year {id, year, name, start_date, end_date}. Field fiscal_year_id pada body adalah pilihan transaksi. Query/header adalah konteks halaman, bukan sumber penimpaan transaksi.
- Create tanpa pilihan tahun memerlukan tepat satu periode yang mencakup Journal Date. Edit tanpa perubahan tahun mempertahankan tahun tersimpan, meskipun filter global berbeda. Tanggal di luar tahun, periode closed sesuai kebijakan hard lock, dan periode ambigu menghasilkan 422. Posted tetap tidak bisa diedit langsung.
- journals.fiscal_year_id sudah mempunyai FK ke fiscal_years, nullable, nullOnDelete, serta index dari migration 2026_10_01_000004. Struktur ini juga diperiksa pada database test. Tidak dibuat tabel tahun atau migration schema duplikat. Skema database MySQL live belum dapat dibaca.
- Journal list Semua Tahun tidak mengikuti default tahun aktif. Tahun pilihan diterapkan eksplisit; binding/detail mengacu tahun transaksi. Budget Monitoring tetap hanya memakai journal posted pada journals.fiscal_year_id yang diminta dan budget pada tahun budget yang sesuai. Pengujian sebelumnya untuk bank/accrued payable/relasi tetap dijalankan.

## File perubahan pada pekerjaan approval/tahun

| File | Tujuan |
| --- | --- |
| app/Http/Controllers/Api/Accounting/JournalController.php | Transaksi submit/post, guard sebelum mutasi, payload dan response fiscal year, query tahun eksplisit |
| app/Http/Controllers/Api/ApprovalCenterController.php | History memakai progress yang sama dan pembatasan akses journal |
| app/Models/Accounting/Journal.php | Binding transaksi tidak tergantung filter tahun halaman |
| app/Models/ApprovalWorkflowAction.php | Relasi matrix tidak disembunyikan oleh konteks tahun halaman |
| app/Services/Approval/ApprovalWorkflowService.php | Resolver assignment bersama, snapshot history, capability, scope dan stage aktif |
| app/Services/Approval/ApprovalIdentityResolver.php | Identitas canonical berbasis ID dan fallback email unik |
| app/Services/Accounting/JournalFiscalYearService.php | Resolusi periode unik, validasi tanggal dan preservasi tahun edit |
| app/Services/Accounting/JournalPostingGuard.php | Prasyarat posting yang dipakai endpoint serta capability |
| app/Console/Commands/AuditJournalApproval.php | Audit read-only journal/run/action/matrix/ID user-employee/FK/index |
| app/Console/Commands/BackfillJournalFiscalYears.php | Dry-run default; isi hanya tahun kosong yang periodenya unik; laporkan konflik |
| database/migrations/2026_10_01_000005_backfill_transaction_fiscal_years.php | Tidak lagi menebak tahun journal pada instalasi baru |
| tests/Feature/JournalApprovalFiscalYearTest.php | Approval identity, scope, rollback, fiscal year dan command |
| tests/Feature/JournalWorkflowTest.php | Fixture mempunyai tahun transaksi yang valid |
| docs/JOURNAL_APPROVAL_FISCAL_YEAR_AUDIT.md | Laporan ini |

Perubahan integrasi Budget Monitoring pada pekerjaan sebelumnya tetap dipertahankan; lihat JOURNAL_BUDGET_INTEGRATION_AUDIT.md. Frontend tidak diubah pada pekerjaan backend ini.

## Temuan frontend aktual

Sumber dibaca dari C:\Users\raulm\Downloads\Frontend-KT, bukan salinan Frontend-KT di dalam backend.

JournalPage.jsx sudah mempunyai pemilihan/validasi tanggal Fiscal Year, mengisi edit dari journal.fiscal_year_id/fiscal_year.id, kolom Fiscal Year, serta label Reviewed - Belum Posted. Namun action sekitar baris 921-923 masih hanya disabled berdasarkan !isReviewed, bukan can_approve/can_post backend. Karena itu FE belum dapat dinyatakan selesai terintegrasi.

Kontrak yang harus dipakai FE:

- Bila approval_progress.status=in_progress, aktifkan Approve Tahap Aktif hanya jika approval_progress.can_approve=true.
- Setelah workflow approved, atau bila memang tidak memerlukan workflow, aktifkan Post to Ledger hanya jika approval_progress.can_post=true.
- Tampilkan can_approve_reason/can_post_reason atau unavailable_reason bila tombol nonaktif.
- Tampilkan status journal terpisah dari status workflow; approved workflow tidak otomatis menjadi label posted transaksi.
- Saat edit, kirim tahun tersimpan dari transaksi; jangan menggantinya dengan tahun filter halaman. Kirim kosong/tidak ada fiscal_year_id pada filter Semua Tahun.

## Deployment dan verifikasi live

1. Deploy file backend yang berubah. Tidak ada migration schema baru. Periksa migration 2026_10_01_000004 sudah diterapkan; jalankan migration pending sesuai prosedur deployment. Jangan rollback/rerun migration historis yang sudah diterapkan.
2. Jalankan php artisan optimize:clear dan restart proses/worker backend sesuai deployment.
3. Setelah MySQL bisa diakses, jalankan audit read-only berikut:

```powershell
php artisan journals:audit-approval "CDVRUU 2611-01" --user-name="Syaifani Auliana Havid"
```

Periksa kandidat user, stored_employee_id/resolved_employee_id, permission, snapshot action, matrix scope, status dan level. Bila ada beberapa user bernama sama, nama hanya untuk pencarian; ulangi dengan --user-id=ID_YANG_SUDAH_DIVERIFIKASI. Command tidak memilih atau mengubah assignment. Konflik snapshot vs matrix harus ditinjau sebelum repair terarah; tidak ada repair produksi otomatis pada perubahan ini.

4. Untuk data tahun lama:

```powershell
php artisan journals:backfill-fiscal-years --dry-run
```

Command melaporkan periode ambigu serta tahun tersimpan yang tidak sesuai tanggal, tanpa menimpa nilai tersimpan. Setelah hasil ditinjau, --apply hanya mengisi tahun yang kosong dan unik; --dry-run mengalahkan --apply bila keduanya diberikan. Command idempotent, tidak mengubah nominal/status dan tidak melakukan approval. Jangan memakai hasil command sebagai cara mengubah tahun posted yang sudah terisi.

5. Sinkronkan action FE dengan capability backend sebelum menganggap pengalaman approval selesai. Verifikasi staging memakai ID user asli, minimal satu intermediate approval dan final posting secara terkontrol; tidak ada mass approval/posting.
6. Jalankan test normal dengan php artisan test. Pada sandbox Windows ini PHPUnit melaporkan is_readable=false walaupun require/fopen berhasil; pengujian lokal menggunakan launcher sementara yang menyesuaikan hanya pemeriksaan preflight di memori. File vendor tidak diubah.

## Hasil pemeriksaan akhir

- Syntax check: seluruh 22 file PHP yang berubah/baru pada workspace backend lulus, termasuk perubahan integrasi dari pekerjaan sebelumnya.
- Test terarah: 23 tests, 187 assertions, seluruhnya lulus. Termasuk assignment user/employee, nama/role yang sama tetapi ID berbeda, requester/self-approval, snapshot vs matrix, intermediate approval, rollback saat final save gagal, scope dan permission, fiscal year edit/global filter, closed/mismatched/ambiguous year, dry-run/idempotensi, serta posted tahun lain tidak masuk actual.
- Suite lengkap akhir: 109 tests, 801 assertions; 98 lulus, 9 failure dan 2 error. Perbandingan daftar test gagal/error terhadap hasil sebelum pekerjaan approval/tahun menunjukkan tidak ada nama test gagal baru atau yang hilang. Kegagalan yang tersisa sama: ApprovalCenterAndAttachment (1), ApprovalWorkflow (1), CashAdvanceWorkflow (4), EndToEndDonorToReport (1), ExpenseWorkflow (2), serta error BankAccountOpeningBalancePersistence dan DropdownContract.
- git diff --check lulus; terdapat pemberitahuan normalisasi line ending Git, tanpa whitespace error.
- Command audit live dicoba kembali setelah environment tersambung; masih gagal pada koneksi MySQL sebelum journal dibaca. Oleh sebab itu ID database live, assignment konkret Syaifani dan hasil approval journal CDVRUU 2611-01 belum diklaim terverifikasi.
- Frontend hanya dibaca. Tidak ada klaim build/test/UI frontend atau deployment yang selesai.

## Tindak lanjut: Journal Date dan Fiscal Year tetap terpisah

Backend mempertahankan journal_date sebagai tanggal transaksi dan fiscal_year_id sebagai FK periode fiskal; keduanya dikembalikan terpisah pada create/update/list/detail. Mengganti tanggal dalam tahun yang sama tidak mengganti tahun tersimpan. Memilih tahun eksplisit tidak mengubah tanggal menjadi awal/akhir periode. Kombinasi yang tidak valid menghasilkan 422 tanpa menyimpan perubahan atau mengoreksi nilai pengguna diam-diam.

JournalFiscalYearService kini menolak fiscal_year_id yang secara eksplisit dikosongkan pada body create/update. Tidak mengirim field tersebut tetap mempunyai kontrak kompatibilitas sebelumnya: edit mempertahankan tahun tersimpan, create menyelesaikan hanya periode tanggal yang unik. Ini berbeda dari filter Semua Tahun yang kosong pada query halaman dan tetap diizinkan.

Verifikasi tindak lanjut: syntax check kedua file PHP lulus, git diff --check lulus, 25 test terkait / 216 assertions seluruhnya lulus. Dua test tambahan membuktikan pemisahan kedua nilai pada create/list/detail/edit dan penolakan pengosongan tahun eksplisit tanpa perubahan data tersimpan. Tidak diperlukan migration tambahan; tidak ada perubahan database aplikasi atau frontend pada tindak lanjut ini. Suite lengkap tidak diulang pada tindak lanjut terbatas ini; hasil suite lengkap terakhir di atas tetap dicatat sebagai hasil run sebelumnya.
