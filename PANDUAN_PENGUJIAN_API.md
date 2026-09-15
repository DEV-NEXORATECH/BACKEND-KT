# Panduan Lengkap Pengujian End-to-End: 30 Modul Master Data (Full CRUD Suite)

Dokumen ini berisi panduan lengkap untuk melakukan pengujian mandiri (*self-testing*) seluruh **30 Modul Master Data Keuangan & ERP Perkumpulan Kaoem Telapak**, mencakup siklus lengkap: **Create (POST), Read List & Detail (GET), Update (PUT), Status Toggle (PATCH), Delete (DELETE), serta Export (CSV & PDF)**.

---

## 📋 1. Persiapan Awal (*Prerequisites*)

### Jalankan Server Backend
Buka terminal dan jalankan perintah:
```bash
cd c:\laragon\www\BACKEND-KT
php artisan serve
```
* **Base URL**: `http://127.0.0.1:8000`
* **Swagger UI Interaktif**: [`http://127.0.0.1:8000/docs`](http://127.0.0.1:8000/docs)
* **Koleksi Postman (Full CRUD)**: [`http://127.0.0.1:8000/Kaoem_Telapak_Master_Data_API.postman_collection.json`](http://127.0.0.1:8000/Kaoem_Telapak_Master_Data_API.postman_collection.json)

---

## 🔐 2. Autentikasi (Login & Dapatkan Token)

Setiap request ke Master Data wajib menyertakan header:
`Authorization: Bearer <TOKEN_ANDA>`

### Endpoint Login
* **URL**: `POST /api/login`
* **Headers**: `Content-Type: application/json`, `Accept: application/json`
* **Body (JSON)**:
```json
{
  "email": "admin@kaoemtelapak.test",
  "password": "password123"
}
```

---

## 🌐 3. Pola Standar Operasi CRUD Seluruh 30 Master Data

Di Postman dan API, setiap modul memiliki **9 request standar**:

1. **`GET /api/v1/master/{resource}?page=1&per_page=10`** $\rightarrow$ Ambil daftar data terpaginasi (Default: 10 data).
2. **`GET /api/v1/master/{resource}?options=true`** $\rightarrow$ Ambil opsi ringan tanpa paginasi untuk dropdown form frontend.
3. **`GET /api/v1/master/{resource}/{id}`** $\rightarrow$ Ambil detail 1 data spesifik berdasarkan ID.
4. **`POST /api/v1/master/{resource}`** $\rightarrow$ Tambah data baru (*Create*).
5. **`PUT /api/v1/master/{resource}/{id}`** $\rightarrow$ Perbarui seluruh/sebagian data (*Update*).
6. **`PATCH /api/v1/master/{resource}/{id}/toggle-status`** $\rightarrow$ Ubah status aktif/nonaktif dalam 1 klik.
7. **`GET /api/v1/master/{resource}/export?format=csv`** $\rightarrow$ Download data format CSV (*Streamed*).
8. **`GET /api/v1/master/{resource}/export?format=pdf`** $\rightarrow$ Download dokumen resmi format PDF (Kop Surat Kaoem Telapak).
9. **`DELETE /api/v1/master/{resource}/{id}`** $\rightarrow$ Hapus data secara aman (*Soft Delete*).

---

## 📚 4. Rincian & Contoh Payload 30 Modul Master Data (Create & Update)

---

### [Kluster 1: Struktur Organisasi]

#### 1. Organization / Entity (`/api/v1/master/organizations`)
* **Create (`POST`)**:
```json
{
  "code": "ORG-KT-02",
  "name": "Yayasan Kaoem Telapak",
  "legal_name": "Yayasan Konservasi Kaoem Telapak",
  "npwp": "02.345.678.9-403.000",
  "address": "Jl. Palem No. 20, Bogor",
  "base_currency_id": 1,
  "fiscal_year_start_month": 1,
  "is_active": true
}
```
* **Update (`PUT /1`)**:
```json
{
  "code": "ORG-KT-02",
  "name": "Yayasan Kaoem Telapak Indonesia",
  "legal_name": "Yayasan Konservasi Kaoem Telapak Indonesia",
  "npwp": "02.345.678.9-403.000",
  "address": "Jl. Palem No. 20, Baranangsiang Indah, Bogor",
  "base_currency_id": 1,
  "fiscal_year_start_month": 1,
  "is_active": true
}
```

#### 2. Office / Branch / Location (`/api/v1/master/office-locations`)
* **Create (`POST`)**:
```json
{
  "organization_id": 1,
  "code": "FO-MDN",
  "name": "Field Office Medan",
  "address": "Jl. Gatot Subroto No. 12, Medan",
  "pic_name": "Rahmat Hidayat",
  "phone": "061-8877665",
  "email": "medan@kaoemtelapak.org",
  "is_head_office": false,
  "is_active": true
}
```
* **Update (`PUT /1`)**:
```json
{
  "organization_id": 1,
  "code": "FO-MDN",
  "name": "Field Office Medan - Sumatera Utara",
  "address": "Jl. Gatot Subroto No. 12, Medan",
  "pic_name": "Rahmat Hidayat Lubis",
  "phone": "061-8877665",
  "email": "medan@kaoemtelapak.org",
  "is_head_office": false,
  "is_active": true
}
```

#### 3. Department / Unit (`/api/v1/master/departments`)
* **Create (`POST`)**:
```json
{
  "organization_id": 1,
  "code": "DEPT-HR",
  "name": "Human Resources & General Affairs",
  "manager_name": "Ratna Dewi",
  "is_active": true
}
```
* **Update (`PUT /1`)**:
```json
{
  "organization_id": 1,
  "code": "DEPT-HR",
  "name": "Human Capital & General Affairs",
  "manager_name": "Ratna Dewi",
  "is_active": true
}
```

#### 4. Cost Center (`/api/v1/master/cost-centers`)
* **Create (`POST`)**:
```json
{
  "organization_id": 1,
  "department_id": 1,
  "code": "CC-ADVOCACY",
  "name": "Indigenous Advocacy Cost Center",
  "description": "Pusat biaya advokasi masyarakat adat",
  "is_active": true
}
```
* **Update (`PUT /1`)**:
```json
{
  "organization_id": 1,
  "department_id": 1,
  "code": "CC-ADVOCACY",
  "name": "Policy & Indigenous Advocacy Cost Center",
  "description": "Pusat biaya advokasi hukum dan masyarakat adat",
  "is_active": true
}
```

---

### [Kluster 2: Fondasi Finansial & Akuntansi]

#### 5. Chart of Accounts / COA (`/api/v1/master/chart-of-accounts`)
* **Create (`POST`)**:
```json
{
  "parent_id": 1,
  "code": "1199",
  "name": "Dana Titipan Sementara",
  "account_type": "asset",
  "normal_balance": "debit",
  "level": 3,
  "is_header": false,
  "is_active": true
}
```
* **Update (`PUT /1`)**:
```json
{
  "parent_id": 1,
  "code": "1199",
  "name": "Dana Titipan Sementara Kegiatan",
  "account_type": "asset",
  "normal_balance": "debit",
  "level": 3,
  "is_header": false,
  "is_active": true
}
```

#### 6. Funding Source (`/api/v1/master/funding-sources`)
* **Create (`POST`)**:
```json
{
  "code": "FUND-CSR",
  "name": "Corporate Social Responsibility Fund",
  "funding_type": "CSR",
  "restriction_type": "unrestricted",
  "is_active": true
}
```
* **Update (`PUT /1`)**:
```json
{
  "code": "FUND-CSR",
  "name": "CSR & Private Sector Partnership",
  "funding_type": "CSR",
  "restriction_type": "unrestricted",
  "is_active": true
}
```

#### 7. Donor / Funder (`/api/v1/master/donors`)
* **Create (`POST`)**:
```json
{
  "code": "DONOR-CLUA",
  "name": "Climate and Land Use Alliance (CLUA)",
  "type": "Foundation",
  "country": "United States",
  "contact_person": "Sarah Jenkins",
  "email": "s.jenkins@climateandlandusealliance.org",
  "phone": "+1-415-561-7500",
  "default_currency_id": 2,
  "is_active": true
}
```
* **Update (`PUT /1`)**:
```json
{
  "code": "DONOR-CLUA",
  "name": "Climate and Land Use Alliance (CLUA)",
  "type": "Foundation",
  "country": "United States",
  "contact_person": "Sarah Jenkins (Senior Program Officer)",
  "email": "s.jenkins@climateandlandusealliance.org",
  "phone": "+1-415-561-7500",
  "default_currency_id": 2,
  "is_active": true
}
```

#### 8. Grant / Agreement (`/api/v1/master/grant-agreements`)
* **Create (`POST`)**:
```json
{
  "grant_no": "GRT-2026-CLUA-03",
  "donor_id": 1,
  "funding_source_id": 2,
  "agreement_name": "Land Rights and Forest Tenure Security in Indonesia",
  "start_date": "2026-06-01",
  "end_date": "2027-05-31",
  "currency_id": 2,
  "grant_value": 250000.00,
  "exchange_rate_contract": 16300.00,
  "bank_account_id": 2,
  "status": "active",
  "is_active": true
}
```
* **Update (`PUT /1`)**:
```json
{
  "grant_no": "GRT-2026-CLUA-03",
  "donor_id": 1,
  "funding_source_id": 2,
  "agreement_name": "Land Rights and Forest Tenure Security in Indonesia (Phase 1)",
  "start_date": "2026-06-01",
  "end_date": "2027-05-31",
  "currency_id": 2,
  "grant_value": 275000.00,
  "exchange_rate_contract": 16300.00,
  "bank_account_id": 2,
  "status": "active",
  "is_active": true
}
```

#### 9. Program (`/api/v1/master/programs`)
* **Create (`POST`)**:
```json
{
  "code": "PROG-INDIGENOUS",
  "name": "Indigenous Peoples Rights",
  "objective": "Pengakuan wilayah adat dan pemberdayaan ekonomi komunitas lokal",
  "manager_name": "Siti Nurhaliza",
  "start_date": "2026-01-01",
  "end_date": "2028-12-31",
  "is_active": true
}
```
* **Update (`PUT /1`)**:
```json
{
  "code": "PROG-INDIGENOUS",
  "name": "Indigenous Peoples Rights & Livelihood",
  "objective": "Pengakuan wilayah adat dan penguatan kedaulatan komunitas lokal",
  "manager_name": "Siti Nurhaliza",
  "start_date": "2026-01-01",
  "end_date": "2028-12-31",
  "is_active": true
}
```

#### 10. Project (`/api/v1/master/projects`)
* **Create (`POST`)**:
```json
{
  "code": "PRJ-2026-NICFI-02",
  "program_id": 1,
  "grant_agreement_id": 2,
  "name": "Independent Timber Tracking in Sumatra",
  "manager_name": "Bambang Irawan",
  "start_date": "2026-03-01",
  "end_date": "2027-02-28",
  "budget_currency_id": 2,
  "total_budget": 250000.00,
  "is_active": true
}
```
* **Update (`PUT /1`)**:
```json
{
  "code": "PRJ-2026-NICFI-02",
  "program_id": 1,
  "grant_agreement_id": 2,
  "name": "Independent Timber Tracking & Trade Monitoring in Sumatra",
  "manager_name": "Bambang Irawan",
  "start_date": "2026-03-01",
  "end_date": "2027-02-28",
  "budget_currency_id": 2,
  "total_budget": 250000.00,
  "is_active": true
}
```

#### 11. Activity (`/api/v1/master/activities`)
* **Create (`POST`)**:
```json
{
  "project_id": 1,
  "code": "ACT-2026-01-03",
  "name": "Penyusunan Peta Spasial Resolusi Tinggi Hutan Adat",
  "pic_name": "Fajar Nugraha",
  "start_date": "2026-05-01",
  "end_date": "2026-05-30",
  "target_output": "1 Bundel Peta Spasial Terverifikasi BRWA",
  "is_active": true
}
```
* **Update (`PUT /1`)**:
```json
{
  "project_id": 1,
  "code": "ACT-2026-01-03",
  "name": "Penyusunan Peta Spasial Resolusi Tinggi Hutan Adat Terverifikasi",
  "pic_name": "Fajar Nugraha",
  "start_date": "2026-05-01",
  "end_date": "2026-05-30",
  "target_output": "Peta Spasial Terverifikasi BRWA dan BIG",
  "is_active": true
}
```

#### 12. Budget Category (`/api/v1/master/budget-categories`)
* **Create (`POST`)**:
```json
{
  "parent_id": null,
  "code": "BCAT-400",
  "name": "Equipment & Capital Assets",
  "description": "Pengadaan perlengkapan kantor, laptop, GPS, dan drone",
  "is_active": true
}
```
* **Update (`PUT /1`)**:
```json
{
  "parent_id": null,
  "code": "BCAT-400",
  "name": "Field Equipment & Capital Assets",
  "description": "Pengadaan perlengkapan lapangan, GPS, drone, dan laptop",
  "is_active": true
}
```

#### 13. Budget Line / Account Mapping (`/api/v1/master/budget-lines`)
* **Create (`POST`)**:
```json
{
  "grant_agreement_id": 1,
  "project_id": 1,
  "budget_category_id": 1,
  "line_code": "BL-FORD-1.2",
  "description": "GIS Specialist Honorarium (12 Months)",
  "unit_of_measure_id": 2,
  "unit_price": 1500.00,
  "quantity": 12.00,
  "total_amount": 18000.00,
  "gl_account_id": 19,
  "is_active": true
}
```
* **Update (`PUT /1`)**:
```json
{
  "grant_agreement_id": 1,
  "project_id": 1,
  "budget_category_id": 1,
  "line_code": "BL-FORD-1.2",
  "description": "Senior GIS Specialist Honorarium (12 Months)",
  "unit_of_measure_id": 2,
  "unit_price": 1500.00,
  "quantity": 12.00,
  "total_amount": 18000.00,
  "gl_account_id": 19,
  "is_active": true
}
```

#### 14. Fiscal Year (`/api/v1/master/fiscal-years`)
* **Create (`POST`)**:
```json
{
  "year": 2027,
  "name": "Tahun Fiskal 2027",
  "start_date": "2027-01-01",
  "end_date": "2027-12-31",
  "status": "open",
  "is_active": true
}
```
* **Update (`PUT /1`)**:
```json
{
  "year": 2027,
  "name": "Tahun Fiskal Anggaran 2027",
  "start_date": "2027-01-01",
  "end_date": "2027-12-31",
  "status": "open",
  "is_active": true
}
```

#### 15. Currency (`/api/v1/master/currencies`)
* **Create (`POST`)**:
```json
{
  "code": "GBP",
  "name": "British Pound Sterling",
  "symbol": "£",
  "decimal_places": 2,
  "is_base_currency": false,
  "is_active": true
}
```
* **Update (`PUT /1`)**:
```json
{
  "code": "GBP",
  "name": "Great British Pound",
  "symbol": "£",
  "decimal_places": 2,
  "is_base_currency": false,
  "is_active": true
}
```

#### 16. Exchange Rate (`/api/v1/master/exchange-rates`)
* **Create (`POST`)**:
```json
{
  "date": "2026-09-15",
  "from_currency_id": 2,
  "to_currency_id": 1,
  "rate": 16275.50,
  "source": "Pajak / Kemenkeu",
  "is_active": true
}
```
* **Update (`PUT /1`)**:
```json
{
  "date": "2026-09-15",
  "from_currency_id": 2,
  "to_currency_id": 1,
  "rate": 16300.00,
  "source": "Pajak / Kemenkeu KMK",
  "is_active": true
}
```

#### 17. Vendor / Supplier (`/api/v1/master/vendors`)
* **Create (`POST`)**:
```json
{
  "code": "VND-CAR-03",
  "name": "PT Rental Borneo Sejahtera",
  "type": "company",
  "npwp": "03.789.456.1-701.000",
  "address": "Jl. Imam Bonjol No. 99, Pontianak",
  "contact_person": "Dedi Kurniawan",
  "phone": "0561-889900",
  "email": "rental@borneosejahtera.co.id",
  "bank_name": "Bank Mandiri",
  "bank_account_number": "146-00-554422-1",
  "bank_account_holder": "PT Rental Borneo Sejahtera",
  "tax_id": 3,
  "is_active": true
}
```
* **Update (`PUT /1`)**:
```json
{
  "code": "VND-CAR-03",
  "name": "PT Rental Mobil Borneo Sejahtera",
  "type": "company",
  "npwp": "03.789.456.1-701.000",
  "address": "Jl. Imam Bonjol No. 99, Pontianak",
  "contact_person": "Dedi Kurniawan",
  "phone": "0561-889900",
  "email": "rental@borneosejahtera.co.id",
  "bank_name": "Bank Mandiri",
  "bank_account_number": "146-00-554422-1",
  "bank_account_holder": "PT Rental Mobil Borneo Sejahtera",
  "tax_id": 3,
  "is_active": true
}
```

#### 18. Employee / Staff (`/api/v1/master/employees`)
* **Create (`POST`)**:
```json
{
  "employee_id_number": "KT-EMP-004",
  "name": "Dina Amalia",
  "email": "dina.amalia@kaoemtelapak.org",
  "department_id": 2,
  "office_location_id": 1,
  "position": "Finance & Cashier Officer",
  "bank_name": "Bank BCA",
  "bank_account_number": "889-03-445566-7",
  "bank_account_holder": "Dina Amalia",
  "is_active": true
}
```
* **Update (`PUT /1`)**:
```json
{
  "employee_id_number": "KT-EMP-004",
  "name": "Dina Amalia Putri",
  "email": "dina.amalia@kaoemtelapak.org",
  "department_id": 2,
  "office_location_id": 1,
  "position": "Senior Finance & Cashier Officer",
  "bank_name": "Bank BCA",
  "bank_account_number": "889-03-445566-7",
  "bank_account_holder": "Dina Amalia Putri",
  "is_active": true
}
```

#### 19. Beneficiary / Partner (`/api/v1/master/beneficiary-partners`)
* **Create (`POST`)**:
```json
{
  "code": "PART-LPHD",
  "name": "Lembaga Pengelola Hutan Desa Bentayan",
  "type": "beneficiary_group",
  "address": "Desa Bentayan, Kab. Sintang, Kalbar",
  "pic_name": "Darius Mura",
  "contact_info": "0852-9988-7766",
  "bank_info": "Bank Kalbar 102-3344-55 a/n LPHD Bentayan",
  "is_active": true
}
```
* **Update (`PUT /1`)**:
```json
{
  "code": "PART-LPHD",
  "name": "Lembaga Pengelola Hutan Desa Bentayan Mandiri",
  "type": "beneficiary_group",
  "address": "Desa Bentayan, Kab. Sintang, Kalbar",
  "pic_name": "Darius Mura",
  "contact_info": "0852-9988-7766",
  "bank_info": "Bank Kalbar 102-3344-55 a/n LPHD Bentayan Mandiri",
  "is_active": true
}
```

#### 20. Bank Account (`/api/v1/master/bank-accounts`)
* **Create (`POST`)**:
```json
{
  "organization_id": 1,
  "bank_name": "Bank BNI 46",
  "account_number": "098-765-4321",
  "account_name": "Perkumpulan Kaoem Telapak - Operasional",
  "swift_code": "BNINIDJA",
  "currency_id": 1,
  "gl_account_id": 4,
  "is_active": true
}
```
* **Update (`PUT /1`)**:
```json
{
  "organization_id": 1,
  "bank_name": "Bank BNI",
  "account_number": "098-765-4321",
  "account_name": "Perkumpulan Kaoem Telapak - Rekening Operasional BNI",
  "swift_code": "BNINIDJA",
  "currency_id": 1,
  "gl_account_id": 4,
  "is_active": true
}
```

#### 21. Cash / Petty Cash (`/api/v1/master/petty-cashes`)
* **Create (`POST`)**:
```json
{
  "organization_id": 1,
  "office_location_id": 2,
  "code": "PC-PTK",
  "name": "Kas Kecil Kantor Pontianak",
  "custodian_name": "Budi Santoso",
  "currency_id": 1,
  "limit_amount": 5000000.00,
  "gl_account_id": 3,
  "is_active": true
}
```
* **Update (`PUT /1`)**:
```json
{
  "organization_id": 1,
  "office_location_id": 2,
  "code": "PC-PTK",
  "name": "Kas Kecil Kantor Pontianak Kalbar",
  "custodian_name": "Budi Santoso",
  "currency_id": 1,
  "limit_amount": 7500000.00,
  "gl_account_id": 3,
  "is_active": true
}
```

#### 22. Expense Category (`/api/v1/master/expense-categories`)
* **Create (`POST`)**:
```json
{
  "parent_id": null,
  "code": "EXP-SUPPLIES",
  "name": "Alat Tulis Kantor & Supplies",
  "default_gl_account_id": 22,
  "is_taxable": true,
  "requires_receipt": true,
  "requires_advance_settlement": false,
  "is_active": true
}
```
* **Update (`PUT /1`)**:
```json
{
  "parent_id": null,
  "code": "EXP-SUPPLIES",
  "name": "ATK, Kertas & Perlengkapan Kantor",
  "default_gl_account_id": 22,
  "is_taxable": true,
  "requires_receipt": true,
  "requires_advance_settlement": false,
  "is_active": true
}
```

#### 23. Tax (`/api/v1/master/taxes`)
* **Create (`POST`)**:
```json
{
  "code": "PPH-4(2)",
  "name": "PPh Pasal 4 Ayat 2 (Sewa Gedung/Kantor)",
  "tax_type": "PPH4_2",
  "rate_percent": 10.00,
  "purchase_gl_account_id": 11,
  "is_active": true
}
```
* **Update (`PUT /1`)**:
```json
{
  "code": "PPH-4(2)",
  "name": "PPh Pasal 4 Ayat 2 Final (Sewa Bangunan & Gedung)",
  "tax_type": "PPH4_2",
  "rate_percent": 10.00,
  "purchase_gl_account_id": 11,
  "is_active": true
}
```

#### 24. Document Type (`/api/v1/master/document-types`)
* **Create (`POST`)**:
```json
{
  "code": "DOC-CONTRACT",
  "name": "Surat Perjanjian Kerja / Kontrak Konsultan",
  "description": "Kontrak bertanda tangan kedua belah pihak bermaterai",
  "is_mandatory_for_payout": true,
  "is_active": true
}
```
* **Update (`PUT /1`)**:
```json
{
  "code": "DOC-CONTRACT",
  "name": "Surat Perjanjian Kerja / Kontrak Konsultan Resmi",
  "description": "Kontrak bertanda tangan kedua belah pihak bermaterai Rp 10.000",
  "is_mandatory_for_payout": true,
  "is_active": true
}
```

#### 25. Payment Method (`/api/v1/master/payment-methods`)
* **Create (`POST`)**:
```json
{
  "code": "PM-CHEQUE",
  "name": "Bank Cheque / Bilyet Giro",
  "type": "bank_transfer",
  "default_gl_account_id": 4,
  "is_active": true
}
```
* **Update (`PUT /1`)**:
```json
{
  "code": "PM-CHEQUE",
  "name": "Cek Bank / Bilyet Giro Mandiri",
  "type": "bank_transfer",
  "default_gl_account_id": 4,
  "is_active": true
}
```

#### 26. Approval Matrix (`/api/v1/master/approval-matrices`)
* **Create (`POST`)**:
```json
{
  "module": "purchase_order",
  "min_amount": 10000000.00,
  "max_amount": 100000000.00,
  "level": 2,
  "role_id": 1,
  "is_conditional_project_manager": false,
  "is_active": true
}
```
* **Update (`PUT /1`)**:
```json
{
  "module": "purchase_order",
  "min_amount": 10000000.00,
  "max_amount": 150000000.00,
  "level": 2,
  "role_id": 1,
  "is_conditional_project_manager": false,
  "is_active": true
}
```

#### 28. Asset Category (`/api/v1/master/asset-categories`)
* **Create (`POST`)**:
```json
{
  "code": "ASSET-FURNITURE",
  "name": "Meja, Kursi & Furniture Kantor",
  "useful_life_months": 48,
  "depreciation_method": "straight_line",
  "asset_gl_account_id": 7,
  "depreciation_gl_account_id": 23,
  "accumulated_gl_account_id": 8,
  "is_active": true
}
```
* **Update (`PUT /1`)**:
```json
{
  "code": "ASSET-FURNITURE",
  "name": "Furniture & Perlengkapan Interior Kantor",
  "useful_life_months": 48,
  "depreciation_method": "straight_line",
  "asset_gl_account_id": 7,
  "depreciation_gl_account_id": 23,
  "accumulated_gl_account_id": 8,
  "is_active": true
}
```

#### 29. Unit of Measure (`/api/v1/master/unit-of-measures`)
* **Create (`POST`)**:
```json
{
  "code": "DAY",
  "name": "Person-Day / Hari Kerja",
  "category": "Time",
  "is_active": true
}
```
* **Update (`PUT /1`)**:
```json
{
  "code": "DAY",
  "name": "Person-Day (Hari Kerja Lapangan)",
  "category": "Time",
  "is_active": true
}
```

#### 30. Reporting Dimension (`/api/v1/master/reporting-dimensions`)
* **Create (`POST`)**:
```json
{
  "dimension_type": "SDG Goal",
  "code": "SDG-13",
  "name": "SDG 13: Climate Action",
  "description": "Aksi penanganan perubahan iklim dan penurunan emisi deforestasi",
  "is_active": true
}
```
* **Update (`PUT /1`)**:
```json
{
  "dimension_type": "SDG Goal",
  "code": "SDG-13",
  "name": "SDG 13: Climate Action (Mitigasi Iklim)",
  "description": "Aksi mitigasi penanganan perubahan iklim dan penurunan emisi deforestasi",
  "is_active": true
}
```

---

## 🧪 5. Skenario Pengujian Mandiri Lengkap (Checklist)

1. [ ] **Login**: Panggil `POST /api/login` $\rightarrow$ Copy token.
2. [ ] **List (Pagination 10)**: Panggil `GET /api/v1/master/chart-of-accounts` $\rightarrow$ Verifikasi 10 data per halaman.
3. [ ] **Mode Dropdown**: Panggil `GET /api/v1/master/currencies?options=true` $\rightarrow$ Verifikasi semua mata uang muncul tanpa paginasi.
4. [ ] **Smart Search**: Panggil `GET /api/v1/master/donors?search=Ford` $\rightarrow$ Verifikasi data terfilter.
5. [ ] **Create (POST)**: Panggil `POST /api/v1/master/vendors` dengan body data baru $\rightarrow$ Status `201 Created`.
6. [ ] **Detail (GET)**: Panggil `GET /api/v1/master/vendors/{id}` $\rightarrow$ Verifikasi data yang baru dibuat.
7. [ ] **Update (PUT)**: Panggil `PUT /api/v1/master/vendors/{id}` dengan perubahan nama $\rightarrow$ Status `200 OK`.
8. [ ] **Toggle Status (PATCH)**: Panggil `PATCH /api/v1/master/vendors/{id}/toggle-status` $\rightarrow$ Status `is_active` berubah.
9. [ ] **Export CSV**: Panggil `GET /api/v1/master/budget-lines/export?format=csv` $\rightarrow$ File `.csv` terdownload.
10. [ ] **Export PDF**: Panggil `GET /api/v1/master/chart-of-accounts/export?format=pdf` $\rightarrow$ File `.pdf` terdownload.
11. [ ] **Delete (DELETE)**: Panggil `DELETE /api/v1/master/vendors/{id}` $\rightarrow$ Data terhapus secara soft delete.
