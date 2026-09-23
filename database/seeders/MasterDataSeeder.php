<?php

namespace Database\Seeders;

use App\Models\Accounting\Journal;
use App\Models\Accounting\JournalLine;
use App\Models\Budget\BudgetCommitment;
use App\Models\Notification;
use App\Models\Master\Activity;
use App\Models\Master\AccountCategory;
use App\Models\Master\ApprovalMatrix;
use App\Models\Master\AssetCategory;
use App\Models\Master\BankAccount;
use App\Models\Master\BeneficiaryPartner;
use App\Models\Master\BudgetCategory;
use App\Models\Master\BudgetLine;
use App\Models\Master\ChartOfAccount;
use App\Models\Master\CostCenter;
use App\Models\Master\Currency;
use App\Models\Master\Department;
use App\Models\Master\DocumentType;
use App\Models\Master\Donor;
use App\Models\Master\Employee;
use App\Models\Master\ExchangeRate;
use App\Models\Master\ExpenseCategory;
use App\Models\Master\FiscalYear;
use App\Models\Master\FundingSource;
use App\Models\Master\GrantAgreement;
use App\Models\Master\OfficeLocation;
use App\Models\Master\Organization;
use App\Models\Master\PaymentMethod;
use App\Models\Master\PettyCash;
use App\Models\Master\Program;
use App\Models\Master\Project;
use App\Models\Master\Position;
use App\Models\Master\ProcurementCategory;
use App\Models\Master\ProcurementItem;
use App\Models\Master\ReportingDimension;
use App\Models\Master\Tax;
use App\Models\Master\UnitOfMeasure;
use App\Models\Master\Vendor;
use App\Models\Master\VendorCategory;
use App\Models\Role;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Currencies (Base foundation)
        $idr = Currency::updateOrCreate(['code' => 'IDR'], [
            'name' => 'Indonesian Rupiah / Rupiah',
            'symbol' => 'Rp',
            'decimal_places' => 0,
            'is_base_currency' => true,
            'is_active' => true,
        ]);

        $usd = Currency::updateOrCreate(['code' => 'USD'], [
            'name' => 'US Dollar',
            'symbol' => '$',
            'decimal_places' => 2,
            'is_base_currency' => false,
            'is_active' => true,
        ]);

        $gbp = Currency::updateOrCreate(['code' => 'GBP'], [
            'name' => 'British Pound Sterling',
            'symbol' => '£',
            'decimal_places' => 2,
            'is_base_currency' => false,
            'is_active' => true,
        ]);

        $nok = Currency::updateOrCreate(['code' => 'NOK'], [
            'name' => 'Norwegian Krone',
            'symbol' => 'kr',
            'decimal_places' => 2,
            'is_base_currency' => false,
            'is_active' => true,
        ]);

        $jpy = Currency::updateOrCreate(['code' => 'JPY'], [
            'name' => 'Japanese Yen',
            'symbol' => '¥',
            'decimal_places' => 0,
            'is_base_currency' => false,
            'is_active' => true,
        ]);

        $eur = Currency::updateOrCreate(['code' => 'EUR'], [
            'name' => 'Euro',
            'symbol' => '€',
            'decimal_places' => 2,
            'is_base_currency' => false,
            'is_active' => true,
        ]);

        $aud = Currency::updateOrCreate(['code' => 'AUD'], [
            'name' => 'Australian Dollar',
            'symbol' => 'A$',
            'decimal_places' => 2,
            'is_base_currency' => false,
            'is_active' => true,
        ]);

        // Exchange Rates to Base Currency (IDR)
        $rates = [
            ['from' => $usd, 'rate' => 16250.000000],
            ['from' => $eur, 'rate' => 17800.000000],
            ['from' => $gbp, 'rate' => 21200.000000],
            ['from' => $nok, 'rate' => 1520.000000],
            ['from' => $jpy, 'rate' => 108.000000],
            ['from' => $aud, 'rate' => 10600.000000],
        ];

        foreach ($rates as $r) {
            ExchangeRate::updateOrCreate([
                'date' => now()->toDateString(),
                'from_currency_id' => $r['from']->id,
                'to_currency_id' => $idr->id,
            ], [
                'rate' => $r['rate'],
                'source' => 'Bank Indonesia JISDOR',
                'is_active' => true,
            ]);
        }

        // 2. Organization & Structure
        $org = Organization::updateOrCreate(['code' => 'KT-ID'], [
            'name' => 'Perkumpulan Kaoem Telapak',
            'legal_name' => 'Perkumpulan Kaoem Telapak Indonesia',
            'npwp' => '01.234.567.8-403.000',
            'address' => 'Jl. Palem No. 18, Baranangsiang Indah, Bogor, Jawa Barat',
            'base_currency_id' => $idr->id,
            'fiscal_year_start_month' => 1,
            'is_active' => true,
        ]);

        $headOffice = OfficeLocation::updateOrCreate(['code' => 'HO-BGR'], [
            'organization_id' => $org->id,
            'name' => 'Head Office Bogor',
            'address' => 'Jl. Palem No. 18, Baranangsiang Indah, Bogor',
            'pic_name' => 'Ahmad Fadhil',
            'phone' => '0251-8312345',
            'email' => 'sekretariat@kaoemtelapak.org',
            'is_head_office' => true,
            'is_active' => true,
        ]);

        $fieldOffice = OfficeLocation::updateOrCreate(['code' => 'FO-PTK'], [
            'organization_id' => $org->id,
            'name' => 'Field Office Pontianak',
            'address' => 'Jl. Danau Sentarum No. 45, Pontianak, Kalimantan Barat',
            'pic_name' => 'Budi Santoso',
            'phone' => '0561-765432',
            'email' => 'pontianak@kaoemtelapak.org',
            'is_head_office' => false,
            'is_active' => true,
        ]);

        $jakartaOffice = OfficeLocation::updateOrCreate(['code' => 'RO-JKT'], [
            'organization_id' => $org->id,
            'name' => 'Kantor Jakarta',
            'address' => 'Jakarta',
            'pic_name' => 'Siti Aminah',
            'phone' => null,
            'email' => 'siti@contoh.org',
            'is_head_office' => false,
            'is_active' => true,
        ]);

        $deptFin = Department::updateOrCreate(['code' => 'Fin'], [
            'organization_id' => $org->id,
            'name' => 'Finance',
            'manager_name' => 'Syaifani Auliana Havid',
            'is_active' => true,
        ]);

        $deptCamp = Department::updateOrCreate(['code' => 'CPG'], [
            'organization_id' => $org->id,
            'name' => 'Campaigner',
            'manager_name' => 'Abil Ahmad Akbar',
            'is_active' => true,
        ]);

        $deptComms = Department::updateOrCreate(['code' => 'COM'], [
            'organization_id' => $org->id,
            'name' => 'Communication',
            'manager_name' => 'Sarah Rosemery Megumi W',
            'is_active' => true,
        ]);

        $deptProc = Department::updateOrCreate(['code' => 'PRC'], [
            'organization_id' => $org->id,
            'name' => 'Procurement & Adm',
            'manager_name' => 'Riki Suryandi',
            'is_active' => true,
        ]);

        $deptProg = Department::updateOrCreate(['code' => 'OPS'], [
            'organization_id' => $org->id,
            'name' => 'Operational',
            'manager_name' => 'Denny Bhatara',
            'is_active' => true,
        ]);

        // Permanently remove legacy DEPT-* departments
        Department::withTrashed()->whereIn('code', ['DEPT-PROG', 'DEPT-FIN', 'DEPT-COMMS', 'DEPT-CAMP'])->forceDelete();

        CostCenter::updateOrCreate(['code' => 'CC-OPS'], [
            'organization_id' => $org->id,
            'department_id' => $deptFin->id,
            'name' => 'General Operations Cost Center',
            'description' => 'Biaya operasional umum dan sekretariat',
            'is_active' => true,
        ]);

        CostCenter::updateOrCreate(['code' => 'CC-FOREST'], [
            'organization_id' => $org->id,
            'department_id' => $deptProg->id,
            'name' => 'Forest Governance Campaign Cost Center',
            'description' => 'Biaya kampanye dan advokasi tata kelola hutan',
            'is_active' => true,
        ]);

        // 3. Fiscal Year & Periods
        $fy2026 = FiscalYear::updateOrCreate(['year' => 2026], [
            'name' => 'Tahun Fiskal 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'open',
            'is_active' => true,
        ]);

        for ($i = 1; $i <= 12; $i++) {
            $monthStr = str_pad($i, 2, '0', STR_PAD_LEFT);
            $monthName = date('F', mktime(0, 0, 0, $i, 10));
            $lastDay = date('t', mktime(0, 0, 0, $i, 1, 2026));

            $fy2026->periods()->updateOrCreate([
                'period_number' => $i,
            ], [
                'name' => "Periode {$monthName} 2026",
                'start_date' => "2026-{$monthStr}-01",
                'end_date' => "2026-{$monthStr}-{$lastDay}",
                'status' => $i <= 9 ? 'open' : 'open',
                'is_active' => true,
            ]);
        }

        // 4. Chart of Accounts
        // Account categories are maintained separately so the COA setup can
        // classify accounts consistently from the first seed run.
        foreach ([
            ['code' => 'ASSET', 'name' => 'Assets', 'account_type' => 'asset'],
            ['code' => 'LIABILITY', 'name' => 'Liabilities', 'account_type' => 'liability'],
            ['code' => 'EQUITY', 'name' => 'Equity', 'account_type' => 'equity'],
            ['code' => 'REVENUE', 'name' => 'Revenue', 'account_type' => 'revenue'],
            ['code' => 'EXPENSE', 'name' => 'Expenses', 'account_type' => 'expense'],
        ] as $category) {
            AccountCategory::updateOrCreate(['code' => $category['code']], [
                'name' => $category['name'],
                'account_type' => $category['account_type'],
                'is_active' => true,
            ]);
        }

        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        \DB::table('journal_lines')->truncate();
        \DB::table('journals')->truncate();
        ChartOfAccount::truncate();
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

        $coaList = [
            ['code' => '10000', 'name' => 'BANK AND CASH', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 1, 'is_header' => true, 'parent_code' => null],
            ['code' => '10100', 'name' => 'Cash', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 2, 'is_header' => true, 'parent_code' => '10000'],
            ['code' => '10110', 'name' => 'Petty Cash', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 3, 'is_header' => false, 'parent_code' => '10100'],
            ['code' => '10120', 'name' => 'Cash on Hand', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 3, 'is_header' => false, 'parent_code' => '10100'],
            ['code' => '10200', 'name' => 'BANK BNI', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 2, 'is_header' => true, 'parent_code' => '10000'],
            ['code' => '10210', 'name' => 'BNI Sekretariat 1 - 460924325', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 3, 'is_header' => false, 'parent_code' => '10200'],
            ['code' => '10212', 'name' => 'BNI Norad EIA - 737971179', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 3, 'is_header' => false, 'parent_code' => '10200'],
            ['code' => '10213', 'name' => 'BNI Norad Fern - 7379666219', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 3, 'is_header' => false, 'parent_code' => '10200'],
            ['code' => '10214', 'name' => 'BNI Norad Samdhana - 628472846', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 3, 'is_header' => false, 'parent_code' => '10200'],
            ['code' => '10215', 'name' => 'BNI Packard - 583418434', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 3, 'is_header' => false, 'parent_code' => '10200'],
            ['code' => '10216', 'name' => 'BNI TET - 460924700', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 3, 'is_header' => false, 'parent_code' => '10200'],
            ['code' => '10217', 'name' => 'BNI FGMC EIA - 1821056816', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 3, 'is_header' => false, 'parent_code' => '10200'],
            ['code' => '10218', 'name' => 'BNI USAID - 182156678', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 3, 'is_header' => false, 'parent_code' => '10200'],
            ['code' => '10219', 'name' => 'BNI Waterloo - 1785559365', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 3, 'is_header' => false, 'parent_code' => '10200'],
            ['code' => '10220', 'name' => 'BNI Sekretariat 3 - 460924700', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 3, 'is_header' => false, 'parent_code' => '10200'],
            ['code' => '10221', 'name' => 'BNI Sekretariat 4 - 737966602', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 3, 'is_header' => false, 'parent_code' => '10200'],
            ['code' => '10222', 'name' => 'BNI Sekretariat 5 - 1785559592', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 3, 'is_header' => false, 'parent_code' => '10200'],
            ['code' => '10223', 'name' => 'BNI Sekretariat 2 - 1821056678', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 3, 'is_header' => false, 'parent_code' => '10200'],
            ['code' => '10224', 'name' => 'BNI FCDO UK Embassy - 583418434', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 3, 'is_header' => false, 'parent_code' => '10200'],
            ['code' => '10225', 'name' => 'BNI FGMC EIA 2 - 1821056816', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 3, 'is_header' => false, 'parent_code' => '10200'],
            ['code' => '10226', 'name' => 'BNI Waterloo 2 - 1785559365', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 3, 'is_header' => false, 'parent_code' => '10200'],
            ['code' => '10227', 'name' => 'BNI Tifa Foundation -  460924700', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 3, 'is_header' => false, 'parent_code' => '10200'],
            ['code' => '10300', 'name' => 'BANK CIMBNIAGA', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 2, 'is_header' => true, 'parent_code' => '10000'],
            ['code' => '10310', 'name' => 'Cimbniaga Sekretariat', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 3, 'is_header' => false, 'parent_code' => '10300'],
            ['code' => '10400', 'name' => 'Bilyet/Giro BNI (Deposito)', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 2, 'is_header' => false, 'parent_code' => '10000'],
            ['code' => '10500', 'name' => 'Bilyet/Giro Cimbniaga (Deposito)', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 2, 'is_header' => false, 'parent_code' => '10000'],
            ['code' => '11000', 'name' => 'ACCOUNTS RECEIVABLE', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 1, 'is_header' => true, 'parent_code' => null],
            ['code' => '11400', 'name' => 'AR PROJECT', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 2, 'is_header' => true, 'parent_code' => '11000'],
            ['code' => '11410', 'name' => 'AR FGMC EIA', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 3, 'is_header' => false, 'parent_code' => '11400'],
        ];

        $createdCoa = [];
        foreach ($coaList as $c) {
            $parentId = isset($c['parent_code']) && isset($createdCoa[$c['parent_code']]) ? $createdCoa[$c['parent_code']]->id : null;
            $coa = ChartOfAccount::updateOrCreate(['code' => $c['code']], [
                'name' => $c['name'],
                'account_type' => $c['account_type'],
                'normal_balance' => $c['normal_balance'],
                'level' => $c['level'],
                'is_header' => $c['is_header'],
                'parent_id' => $parentId,
                'is_active' => true,
            ]);
            $createdCoa[$c['code']] = $coa;
        }

        // 5. Taxes
        Tax::where('code', 'PPN-11')->update(['code' => 'PPN11']);
        Tax::where('code', 'PPH-23')->update(['code' => 'PPh 23']);

        $ppn = Tax::updateOrCreate(['code' => 'PPN11'], [
            'name' => 'PPN',
            'tax_type' => 'PPN',
            'rate_percent' => 11.00,
            'description' => 'Tarif PPN 11.00%',
            'sales_gl_account_id' => $createdCoa['2120']->id ?? null,
            'purchase_gl_account_id' => $createdCoa['1150']->id ?? null,
            'is_active' => true,
        ]);

        $pph21Staff = Tax::updateOrCreate(['code' => 'PPh 21 – Staff'], [
            'name' => 'PPh 21',
            'tax_type' => 'Staff / Employee',
            'rate_percent' => 0.00,
            'description' => 'Mengikuti ketentuan tarif TER (Tarif Efektif Rata-rata) sesuai status dan penghasilan',
            'purchase_gl_account_id' => $createdCoa['2120']->id ?? null,
            'is_active' => true,
        ]);

        $pph21NonStaff = Tax::updateOrCreate(['code' => 'PPh 21 – Non Staff'], [
            'name' => 'PPh 21',
            'tax_type' => 'Non-Employee / Professional',
            'rate_percent' => 0.00,
            'description' => 'Mengikuti ketentuan PPh 21 untuk bukan pegawai; menggunakan dasar pengenaan pajak sesuai ketentuan yang berlaku',
            'purchase_gl_account_id' => $createdCoa['2120']->id ?? null,
            'is_active' => true,
        ]);

        $pph23 = Tax::updateOrCreate(['code' => 'PPh 23'], [
            'name' => 'PPh 23',
            'tax_type' => 'Corporate / Service Income',
            'rate_percent' => 2.00,
            'description' => '2% untuk jenis penghasilan yang dikenakan tarif 2%',
            'purchase_gl_account_id' => $createdCoa['2120']->id ?? null,
            'is_active' => true,
        ]);

        $pph42 = Tax::updateOrCreate(['code' => 'PPh 4(2)'], [
            'name' => 'PPh Final Pasal 4(2)',
            'tax_type' => 'Rental of Building/Land',
            'rate_percent' => 10.00,
            'description' => '10% untuk persewaan tanah dan/atau bangunan',
            'purchase_gl_account_id' => $createdCoa['2120']->id ?? null,
            'is_active' => true,
        ]);

        // Clean up legacy PPH-21 code if still present and separate
        Tax::where('code', 'PPH-21')->update(['is_active' => false]);

        // 6. Bank Accounts & Petty Cash
        // Clean up legacy dummy bank accounts
        BankAccount::whereIn('account_number', ['133-00-1234567-8', '889-01-987654-3', '1234567890'])->forceDelete();

        $bankAccountsData = [
            [
                'bank_name' => 'BNI',
                'account_number' => '1785559365',
                'account_name' => 'Perkumpulan Kaoem Telapak',
                'swift_code' => null,
                'description' => 'Waterloo',
            ],
            [
                'bank_name' => 'BNI',
                'account_number' => '1785559592',
                'account_name' => 'Perkumpulan Kaoem Telapak',
                'swift_code' => null,
                'description' => 'Sekretariat 5 (Montpelier)',
            ],
            [
                'bank_name' => 'BNI',
                'account_number' => '1821056678',
                'account_name' => 'Perkumpulan Kaoem Telapak',
                'swift_code' => null,
                'description' => 'Sekretariat 2 (Internal)',
            ],
            [
                'bank_name' => 'BNI',
                'account_number' => '1821056816',
                'account_name' => 'Perkumpulan Kaoem Telapak',
                'swift_code' => null,
                'description' => 'Fgmc',
            ],
            [
                'bank_name' => 'BNI',
                'account_number' => '460924325',
                'account_name' => 'Perkumpulan Kaoem Telapak',
                'swift_code' => null,
                'description' => 'Sekretariat 1',
            ],
            [
                'bank_name' => 'BNI',
                'account_number' => '460924700',
                'account_name' => 'Perkumpulan Kaoem Telapak',
                'swift_code' => null,
                'description' => 'Tifa',
            ],
            [
                'bank_name' => 'BNI',
                'account_number' => '583418434',
                'account_name' => 'Perkumpulan Kaoem Telapak',
                'swift_code' => null,
                'description' => 'FCDO',
            ],
            [
                'bank_name' => 'BNI',
                'account_number' => '583982730',
                'account_name' => 'Perkumpulan Kaoem Telapak',
                'swift_code' => null,
                'description' => 'Kosong',
            ],
            [
                'bank_name' => 'BNI',
                'account_number' => '628472846',
                'account_name' => 'Perkumpulan Kaoem Telapak',
                'swift_code' => null,
                'description' => 'Samdhana',
            ],
            [
                'bank_name' => 'BNI',
                'account_number' => '737966602',
                'account_name' => 'Perkumpulan Kaoem Telapak',
                'swift_code' => null,
                'description' => 'Sekretariat 4 (Kosong)',
            ],
            [
                'bank_name' => 'BNI',
                'account_number' => '7379666219',
                'account_name' => 'Perkumpulan Kaoem Telapak',
                'swift_code' => null,
                'description' => 'Norad Fern',
            ],
            [
                'bank_name' => 'BNI',
                'account_number' => '737971179',
                'account_name' => 'Perkumpulan Kaoem Telapak',
                'swift_code' => null,
                'description' => 'Norad EIA',
            ],
            [
                'bank_name' => 'CIMB',
                'account_number' => '800193159700',
                'account_name' => 'Perkumpulan Kaoem Telapak',
                'swift_code' => 'BNIAIDJA',
                'description' => null,
            ],
            [
                'bank_name' => 'BNI',
                'account_number' => '2058550328',
                'account_name' => 'Perkumpulan Kaoem Telapak',
                'swift_code' => null,
                'description' => 'Norek Deposito',
            ],
        ];

        $bankAccountCoaMap = [
            '1785559365' => '10219', // Waterloo
            '1785559592' => '10222', // Sekretariat 5 (Montpelier)
            '1821056678' => '10223', // Sekretariat 2 (Internal)
            '1821056816' => '10217', // Fgmc
            '460924325'  => '10210', // Sekretariat 1
            '460924700'  => '10227', // Tifa
            '583418434'  => '10224', // FCDO
            '583982730'  => '10200', // Kosong (General BNI)
            '628472846'  => '10214', // Samdhana
            '737966602'  => '10221', // Sekretariat 4 (Kosong)
            '7379666219' => '10213', // Norad Fern
            '737971179'  => '10212', // Norad EIA
            '800193159700'=> '10310', // CIMB Sekretariat
            '2058550328' => '10400', // Norek Deposito
        ];

        $createdBankAccounts = [];
        foreach ($bankAccountsData as $ba) {
            $coaCode = $bankAccountCoaMap[$ba['account_number']] ?? '10200';
            $createdBankAccounts[$ba['account_number']] = BankAccount::updateOrCreate(
                ['account_number' => $ba['account_number']],
                [
                    'organization_id' => $org->id,
                    'bank_name' => $ba['bank_name'],
                    'account_name' => $ba['account_name'],
                    'swift_code' => $ba['swift_code'],
                    'description' => $ba['description'],
                    'currency_id' => $idr->id,
                    'gl_account_id' => $createdCoa[$coaCode]->id ?? null,
                    'is_active' => true,
                ]
            );
        }

        PettyCash::updateOrCreate(['code' => 'PC-BGR'], [
            'organization_id' => $org->id,
            'office_location_id' => $headOffice->id,
            'name' => 'Kas Kecil Sekretariat Bogor',
            'custodian_name' => 'Dina Amalia (Kasir)',
            'currency_id' => $idr->id,
            'limit_amount' => 10000000,
            'gl_account_id' => $createdCoa['10110']->id ?? null,
            'is_active' => true,
        ]);

        // 7. Payment Methods
        PaymentMethod::updateOrCreate(['code' => 'PM-BT'], [
            'name' => 'Bank Transfer (BNI/CIMB)',
            'type' => 'bank_transfer',
            'default_gl_account_id' => $createdCoa['10200']->id ?? null,
            'is_active' => true,
        ]);

        PaymentMethod::updateOrCreate(['code' => 'PM-PC'], [
            'name' => 'Petty Cash Payout',
            'type' => 'petty_cash',
            'default_gl_account_id' => $createdCoa['10110']->id ?? null,
            'is_active' => true,
        ]);

        PaymentMethod::updateOrCreate(['code' => 'PM-CARD'], [
            'name' => 'Corporate Credit Card',
            'type' => 'corporate_card',
            'default_gl_account_id' => null,
            'is_active' => true,
        ]);

        // 8. Funding Sources & Donors
        $fundBilateral = FundingSource::updateOrCreate(['code' => 'FUND-BILATERAL'], [
            'name' => 'Bilateral Government Grant',
            'funding_type' => 'Bilateral',
            'restriction_type' => 'temporarily_restricted',
            'is_active' => true,
        ]);

        $fundFoundation = FundingSource::updateOrCreate(['code' => 'FUND-FOUNDATION'], [
            'name' => 'International Philanthropic Foundation',
            'funding_type' => 'Foundation',
            'restriction_type' => 'temporarily_restricted',
            'is_active' => true,
        ]);

        $donorFord = Donor::updateOrCreate(['code' => 'DONOR-FORD'], [
            'name' => 'Ford Foundation',
            'type' => 'Foundation',
            'country' => 'United States',
            'contact_person' => 'Michael Green (Program Officer)',
            'email' => 'm.green@fordfoundation.org',
            'phone' => '+1-212-573-5000',
            'default_currency_id' => $usd->id,
            'is_active' => true,
        ]);

        $donorNicfi = Donor::updateOrCreate(['code' => 'DONOR-NICFI'], [
            'name' => 'Norway International Climate and Forest Initiative (NICFI)',
            'type' => 'Government',
            'country' => 'Norway',
            'contact_person' => 'Astrid Lindholm',
            'email' => 'post@kld.dep.no',
            'phone' => '+47-22-24-90-90',
            'default_currency_id' => $usd->id,
            'is_active' => true,
        ]);

        // 9. Grant Agreements
        $grantBankAccountId = $createdBankAccounts['800193159700']->id ?? (reset($createdBankAccounts)->id ?? null);

        $grantFord = GrantAgreement::updateOrCreate(['grant_no' => 'GRT-2026-FORD-01'], [
            'donor_id' => $donorFord->id,
            'funding_source_id' => $fundFoundation->id,
            'agreement_name' => 'Strengthening Indigenous Rights and Forest Governance in Borneo',
            'start_date' => '2026-01-01',
            'end_date' => '2027-12-31',
            'currency_id' => $usd->id,
            'grant_value' => 350000.00,
            'exchange_rate_contract' => 16000.000000,
            'bank_account_id' => $grantBankAccountId,
            'status' => 'active',
            'is_active' => true,
        ]);

        $grantNicfi = GrantAgreement::updateOrCreate(['grant_no' => 'GRT-2026-NICFI-02'], [
            'donor_id' => $donorNicfi->id,
            'funding_source_id' => $fundBilateral->id,
            'agreement_name' => 'Independent Forest Monitoring and Supply Chain Transparency',
            'start_date' => '2026-03-01',
            'end_date' => '2028-02-28',
            'currency_id' => $usd->id,
            'grant_value' => 500000.00,
            'exchange_rate_contract' => 16200.000000,
            'bank_account_id' => $grantBankAccountId,
            'status' => 'active',
            'is_active' => true,
        ]);

        // 10. Programs, Projects & Activities
        $progForest = Program::updateOrCreate(['code' => 'PROG-FOREST'], [
            'name' => 'Forest Governance & Law Enforcement',
            'objective' => 'Mendorong transparansi rantai pasok kayu dan perlindungan hutan adat di Indonesia.',
            'manager_name' => 'Hendri Wijaya',
            'start_date' => '2026-01-01',
            'end_date' => '2028-12-31',
            'is_active' => true,
        ]);

        $prjFordBorneo = Project::updateOrCreate(['code' => 'PRJ-2026-FORD-01'], [
            'program_id' => $progForest->id,
            'grant_agreement_id' => $grantFord->id,
            'name' => 'Community Forest Mapping & Legal Recognition in West Kalimantan',
            'manager_name' => 'Hendra Pratama',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'budget_currency_id' => $usd->id,
            'total_budget' => 175000.00,
            'is_active' => true,
        ]);

        Activity::updateOrCreate(['code' => 'ACT-2026-01-01'], [
            'project_id' => $prjFordBorneo->id,
            'name' => 'Pelatihan Pemetaan Partisipatif Wilayah Adat',
            'pic_name' => 'Budi Santoso',
            'start_date' => '2026-02-15',
            'end_date' => '2026-02-20',
            'target_output' => '30 Kader pemetaan adat mampu mengoperasikan drone & GPS',
            'is_active' => true,
        ]);

        Activity::updateOrCreate(['code' => 'ACT-2026-01-02'], [
            'project_id' => $prjFordBorneo->id,
            'name' => 'Fasilitasi Konsultasi Publik dengan Pemda Kabupaten',
            'pic_name' => 'Rina Wijaya',
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-12',
            'target_output' => 'Draft SK Pengakuan Hutan Adat masuk ke meja Bupati',
            'is_active' => true,
        ]);

        // 11. Beneficiary Partners & Reporting Dimensions
        BeneficiaryPartner::updateOrCreate(['code' => 'PARTNER-AMAN'], [
            'name' => 'Pengurus Wilayah AMAN Kalimantan Barat',
            'type' => 'implementing_partner',
            'address' => 'Jl. Purnama 2, Pontianak',
            'pic_name' => 'Agus Salim',
            'contact_info' => '0812-3456-7890 / agus@aman.or.id',
            'bank_info' => 'Bank Mandiri 146-00-987654-1 a/n PW AMAN Kalbar',
            'is_active' => true,
        ]);

        ReportingDimension::updateOrCreate(['dimension_type' => 'SDG Goal', 'code' => 'SDG-15'], [
            'name' => 'SDG 15: Life on Land',
            'description' => 'Target keberlanjutan keanekaragaman hayati dan perlindungan hutan daratan',
            'is_active' => true,
        ]);

        ReportingDimension::updateOrCreate(['dimension_type' => 'Jurisdiction', 'code' => 'JUR-KALBAR'], [
            'name' => 'Provinsi Kalimantan Barat',
            'description' => 'Target capaian yurisdiksi tingkat provinsi',
            'is_active' => true,
        ]);

        // 12. Unit of Measures & Budget Categories
        $uomTrip = UnitOfMeasure::withTrashed()->updateOrCreate(['code' => 'TRIP'], [
            'name' => 'Round Trip',
            'category' => 'Travel',
            'is_active' => true,
            'deleted_at' => null,
        ]);

        $uomMo = UnitOfMeasure::withTrashed()->updateOrCreate(['code' => 'MONTH'], [
            'name' => 'Person-Month',
            'category' => 'Time',
            'is_active' => true,
            'deleted_at' => null,
        ]);

        $uomPkg = UnitOfMeasure::withTrashed()->updateOrCreate(['code' => 'PKG'], [
            'name' => 'Package / Paket',
            'category' => 'Quantity',
            'is_active' => true,
            'deleted_at' => null,
        ]);

        $bcatPersonnel = BudgetCategory::updateOrCreate(['code' => 'BCAT-100'], [
            'name' => 'Personnel & Consultant Fees',
            'description' => 'Gaji staf proyek dan honor tenaga ahli',
            'is_active' => true,
        ]);

        $bcatDirect = BudgetCategory::updateOrCreate(['code' => 'BCAT-200'], [
            'name' => 'Direct Activity & Workshop Costs',
            'description' => 'Biaya pelaksanaan pelatihan, workshop, dan investigasi lapangan',
            'is_active' => true,
        ]);

        $bcatTravel = BudgetCategory::updateOrCreate(['code' => 'BCAT-300'], [
            'name' => 'Travel & Field Accommodation',
            'description' => 'Tiket pesawat, per diem, penginapan lapangan, dan transport lokal',
            'is_active' => true,
        ]);

        // 13. Budget Lines (Mapping between Grant + Project + Category + COA)
        BudgetLine::updateOrCreate(['line_code' => 'BL-FORD-1.1'], [
            'grant_agreement_id' => $grantFord->id,
            'project_id' => $prjFordBorneo->id,
            'budget_category_id' => $bcatPersonnel->id,
            'description' => 'Project Coordinator Salary (12 Months)',
            'unit_of_measure_id' => $uomMo->id,
            'unit_price' => 2000.00,
            'quantity' => 12.00,
            'total_amount' => 24000.00,
            'gl_account_id' => $createdCoa['5100']->id ?? null,
            'is_active' => true,
        ]);

        BudgetLine::updateOrCreate(['line_code' => 'BL-FORD-2.1'], [
            'grant_agreement_id' => $grantFord->id,
            'project_id' => $prjFordBorneo->id,
            'budget_category_id' => $bcatDirect->id,
            'description' => 'Participatory Mapping Workshops (4 Batches)',
            'unit_of_measure_id' => $uomPkg->id,
            'unit_price' => 5000.00,
            'quantity' => 4.00,
            'total_amount' => 20000.00,
            'gl_account_id' => $createdCoa['5200']->id ?? null,
            'is_active' => true,
        ]);

        BudgetLine::updateOrCreate(['line_code' => 'BL-FORD-3.1'], [
            'grant_agreement_id' => $grantFord->id,
            'project_id' => $prjFordBorneo->id,
            'budget_category_id' => $bcatTravel->id,
            'description' => 'Jakarta - Pontianak Field Investigation Trips',
            'unit_of_measure_id' => $uomTrip->id,
            'unit_price' => 600.00,
            'quantity' => 15.00,
            'total_amount' => 9000.00,
            'gl_account_id' => $createdCoa['5300']->id ?? null,
            'is_active' => true,
        ]);

        // 14. Employees & Vendors
        $employeesList = [
            [
                'employee_id_number' => 'EMP001',
                'name' => 'Siti Aminah',
                'email' => 'siti@contoh.org',
                'department_id' => $deptFin->id,
                'office_location_id' => $jakartaOffice->id,
                'position' => 'Finance Staff',
                'bank_name' => 'BCA',
                'bank_account_number' => '1234567890',
                'bank_account_holder' => 'Siti Aminah',
                'project_bank_name' => null,
                'project_bank_account_number' => null,
            ],
            [
                'employee_id_number' => 'ID00012',
                'name' => 'ABIL ACHMAD AKBAR',
                'email' => 'Abil.Akbar@Kaoemtelapak.org',
                'department_id' => $deptCamp->id,
                'office_location_id' => $headOffice->id,
                'position' => 'Campaigns Lead',
                'bank_name' => 'BCA',
                'bank_account_number' => '8720327709',
                'bank_account_holder' => 'Abil Achmad Akbar',
                'project_bank_name' => 'BNI',
                'project_bank_account_number' => '1328161380',
            ],
            [
                'employee_id_number' => 'ID00026',
                'name' => 'AGETHA TRI LESTARI',
                'email' => 'agetha@kaoemtelapak.org',
                'department_id' => $deptCamp->id,
                'office_location_id' => $headOffice->id,
                'position' => 'Youth Engagement Officer',
                'bank_name' => 'BCA',
                'bank_account_number' => '2170405890',
                'bank_account_holder' => 'Agetha Tri Lestari',
                'project_bank_name' => 'BNI',
                'project_bank_account_number' => '1857330913',
            ],
            [
                'employee_id_number' => 'ID00030',
                'name' => 'AYU PERWITOSARI',
                'email' => 'Ayu.Perwitosari@kaoemtelapak.org',
                'department_id' => $deptComms->id,
                'office_location_id' => $headOffice->id,
                'position' => 'Multi Media Content Creator',
                'bank_name' => 'Mandiri',
                'bank_account_number' => '1380014928753',
                'bank_account_holder' => 'Ayu Perwitosari',
                'project_bank_name' => 'BNI',
                'project_bank_account_number' => '1943417991',
            ],
            [
                'employee_id_number' => 'ID00004',
                'name' => 'DENNY BHATARA',
                'email' => 'Denny.Bhatara@kaoemtelapak.org',
                'department_id' => $deptProg->id,
                'office_location_id' => $headOffice->id,
                'position' => 'Program & Operational Manager',
                'bank_name' => 'BNI',
                'bank_account_number' => '0261317272',
                'bank_account_holder' => 'Denny Bhatara',
                'project_bank_name' => 'BNI',
                'project_bank_account_number' => '1328152977',
            ],
            [
                'employee_id_number' => 'ID00031',
                'name' => 'FEBRY RONALDO GINTING',
                'email' => 'Febry@kaoemtelapak.org',
                'department_id' => $deptCamp->id,
                'office_location_id' => $headOffice->id,
                'position' => 'Youth Engagement and Advocacy',
                'bank_name' => 'BRI',
                'bank_account_number' => '703801009299539',
                'bank_account_holder' => 'Febry Ronaldo Ginting',
                'project_bank_name' => 'BNI',
                'project_bank_account_number' => '1951204007',
            ],
            [
                'employee_id_number' => 'ID00003',
                'name' => 'INDRA NUWINATA',
                'email' => 'indra@kaoemtelapak.org',
                'department_id' => $deptProg->id,
                'office_location_id' => $headOffice->id,
                'position' => 'Satpam & OB',
                'bank_name' => 'BNI',
                'bank_account_number' => '1239014175',
                'bank_account_holder' => 'Indra Nuwinata',
                'project_bank_name' => 'BNI',
                'project_bank_account_number' => null,
            ],
            [
                'employee_id_number' => 'ID00005',
                'name' => 'MUNIP',
                'email' => 'munip@kaoemtelapak.org',
                'department_id' => $deptProg->id,
                'office_location_id' => $headOffice->id,
                'position' => 'Senior Administrative Officer',
                'bank_name' => 'BNI',
                'bank_account_number' => '0904746383',
                'bank_account_holder' => 'Munip',
                'project_bank_name' => 'BNI',
                'project_bank_account_number' => '1328157487',
            ],
            [
                'employee_id_number' => 'ID00022',
                'name' => 'RENALDI SASTRA KUSUMAH AJI',
                'email' => 'renaldi.aji@kaoemtelapak.org',
                'department_id' => $deptCamp->id,
                'office_location_id' => $headOffice->id,
                'position' => 'Campaigner',
                'bank_name' => 'BCA',
                'bank_account_number' => '0213876543',
                'bank_account_holder' => 'Renaldi Sastra Kusumah Aji',
                'project_bank_name' => 'BNI',
                'project_bank_account_number' => '1819551430',
            ],
            [
                'employee_id_number' => 'ID00015',
                'name' => 'RIKI SURYANDI',
                'email' => 'riki.suryandi@kaoemtelapak.org',
                'department_id' => $deptProg->id,
                'office_location_id' => $headOffice->id,
                'position' => 'Procurement & Adm Officer',
                'bank_name' => 'BCA',
                'bank_account_number' => '1830445354',
                'bank_account_holder' => 'Riki Suryandi',
                'project_bank_name' => 'BCA',
                'project_bank_account_number' => '7175203613',
            ],
            [
                'employee_id_number' => 'ID00002',
                'name' => 'SARAH ROSEMERY MEGUMI WOUTHUYZEN',
                'email' => 'sarah.megumi@kaoemtelapak.org',
                'department_id' => $deptComms->id,
                'office_location_id' => $headOffice->id,
                'position' => 'Comms Manager',
                'bank_name' => 'BNI',
                'bank_account_number' => '0904850510',
                'bank_account_holder' => 'Sarah Rosemery Megumi',
                'project_bank_name' => 'BNI',
                'project_bank_account_number' => '1328158130',
            ],
            [
                'employee_id_number' => 'ID00023',
                'name' => 'SYAIFANI AULIANA HAVID',
                'email' => 'syaifani.havid@kaoemtelapak.org',
                'department_id' => $deptFin->id,
                'office_location_id' => $headOffice->id,
                'position' => 'Finance Manager',
                'bank_name' => 'BCA',
                'bank_account_number' => '8100872677',
                'bank_account_holder' => 'Syaifani Auliana Havid',
                'project_bank_name' => 'BNI',
                'project_bank_account_number' => '1819551441',
            ],
            [
                'employee_id_number' => 'ID00021',
                'name' => 'VENI OKTARINI SIREGAR',
                'email' => 'veni.siregar@kaoemtelapak.org',
                'department_id' => $deptCamp->id,
                'office_location_id' => $headOffice->id,
                'position' => 'Sr. Campaigner',
                'bank_name' => 'BCA',
                'bank_account_number' => '5420751429',
                'bank_account_holder' => 'Veni Oktarini Siregar',
                'project_bank_name' => 'BNI',
                'project_bank_account_number' => '1819578576',
            ],
            [
                'employee_id_number' => 'ID00027',
                'name' => 'WINDA APRIYANI',
                'email' => 'winda.apriani@kaoemtelapak.org',
                'department_id' => $deptFin->id,
                'office_location_id' => $headOffice->id,
                'position' => 'Accounting Officer',
                'bank_name' => 'CIMB NIAGA',
                'bank_account_number' => '705220122400',
                'bank_account_holder' => 'Winda Apriyani',
                'project_bank_name' => 'BNI',
                'project_bank_account_number' => '1859299528',
            ],
            [
                'employee_id_number' => 'ID00019',
                'name' => 'ZIADATUNNISA ILMI LATIFA',
                'email' => 'ziadatunnisa.latifa@kaoemtelapak.org',
                'department_id' => $deptCamp->id,
                'office_location_id' => $headOffice->id,
                'position' => 'Campaigner',
                'bank_name' => 'BNI',
                'bank_account_number' => '0497474429',
                'bank_account_holder' => 'Ziadatunnisa Ilmi Latifa',
                'project_bank_name' => 'BNI',
                'project_bank_account_number' => '1817587130',
            ],
            [
                'employee_id_number' => 'ID00017',
                'name' => 'ZUFAR FAUZAN ERIMANT',
                'email' => 'zufar.fauzan@kaoemtelapak.org',
                'department_id' => $deptCamp->id,
                'office_location_id' => $headOffice->id,
                'position' => 'Researcher',
                'bank_name' => 'BNI',
                'bank_account_number' => '0758254090',
                'bank_account_holder' => 'Zufar Fauzan Erimant',
                'project_bank_name' => 'BNI',
                'project_bank_account_number' => '1817587390',
            ],
            [
                'employee_id_number' => 'ID00035',
                'name' => 'M. IRBAH MIFTAKHUL HUDA',
                'email' => 'Irbah@kaoemtelapak.org',
                'department_id' => $deptCamp->id,
                'office_location_id' => $headOffice->id,
                'position' => 'GTID Administrator',
                'bank_name' => 'BCA',
                'bank_account_number' => '4260489084',
                'bank_account_holder' => 'M. Irbah Miftakhul Huda',
                'project_bank_name' => 'BNI',
                'project_bank_account_number' => '2024679499',
            ],
            [
                'employee_id_number' => 'ID00034',
                'name' => 'ARIF CANDRA PRASETYA',
                'email' => 'Arif@Kaoemtelapak.org',
                'department_id' => $deptComms->id,
                'office_location_id' => $headOffice->id,
                'position' => 'Communication Officer',
                'bank_name' => 'BCA',
                'bank_account_number' => '8950752948',
                'bank_account_holder' => 'Arif Candra Prasetya',
                'project_bank_name' => 'BNI',
                'project_bank_account_number' => '2024676602',
            ],
            [
                'employee_id_number' => 'ID00036',
                'name' => 'LEORANA SIHOTANG',
                'email' => 'Leona@kaoemtelapak.org',
                'department_id' => $deptCamp->id,
                'office_location_id' => $headOffice->id,
                'position' => 'Campaigner',
                'bank_name' => 'BNI',
                'bank_account_number' => '966601651',
                'bank_account_holder' => 'Leorana Sihotang',
                'project_bank_name' => 'BNI',
                'project_bank_account_number' => null,
            ],
            [
                'employee_id_number' => 'ID00037',
                'name' => 'WINDA ASTUTI',
                'email' => 'Winda.astuti@kaoemtelapak.org',
                'department_id' => $deptFin->id,
                'office_location_id' => $headOffice->id,
                'position' => 'Finance Officer',
                'bank_name' => null,
                'bank_account_number' => '003624997783',
                'bank_account_holder' => 'Winda Astuti',
                'project_bank_name' => 'BNI',
                'project_bank_account_number' => null,
            ],
            [
                'employee_id_number' => 'EMP-TBC-01',
                'name' => 'KWEE VIENA LESTARI TANJUNG',
                'email' => 'viena.tanjung@kaoemtelapak.org',
                'department_id' => $deptCamp->id,
                'office_location_id' => $headOffice->id,
                'position' => 'Sr. Campaigner',
                'bank_name' => null,
                'bank_account_number' => null,
                'bank_account_holder' => 'Kwee Viena Lestari Tanjung',
                'project_bank_name' => 'TBC',
                'project_bank_account_number' => null,
            ],
            [
                'employee_id_number' => 'EMP-TBC-02',
                'name' => 'Senior Campaigner (TBC)',
                'email' => 'campaigner.tbc@kaoemtelapak.org',
                'department_id' => $deptCamp->id,
                'office_location_id' => $headOffice->id,
                'position' => 'Sr. Campaigner',
                'bank_name' => null,
                'bank_account_number' => null,
                'bank_account_holder' => 'TBC',
                'project_bank_name' => 'TBC',
                'project_bank_account_number' => null,
            ],
        ];

        foreach ($employeesList as $empData) {
            $position = Position::withTrashed()->updateOrCreate([
                'name' => $empData['position'],
                'department_id' => $empData['department_id'] ?? null,
            ], [
                'code' => 'POS-'.strtoupper(substr(md5($empData['position'].'|'.($empData['department_id'] ?? '')), 0, 8)),
                'is_active' => true,
                'deleted_at' => null,
            ]);
            Employee::updateOrCreate(
                ['employee_id_number' => $empData['employee_id_number']],
                [...$empData, 'position_id' => $position->id, 'is_active' => true]
            );
        }

        // Procurement reference data is intentionally seeded as reusable master data,
        // then referenced by vendors and transaction lines rather than hardcoded in forms.
        $servicesCategory = ProcurementCategory::withTrashed()->updateOrCreate(['code' => 'SERVICES'], [
            'name' => 'Professional Services',
            'description' => 'Consultancy, facilitation, and other professional services.',
            'is_active' => true,
            'deleted_at' => null,
        ]);
        $goodsCategory = ProcurementCategory::withTrashed()->updateOrCreate(['code' => 'GOODS'], [
            'name' => 'Goods & Supplies',
            'description' => 'Equipment, printed material, and operational supplies.',
            'is_active' => true,
            'deleted_at' => null,
        ]);
        $hotelVendorCategory = VendorCategory::withTrashed()->updateOrCreate(['code' => 'ACCOMMODATION'], [
            'name' => 'Accommodation Provider',
            'description' => 'Hotel and accommodation supplier.',
            'is_active' => true,
            'deleted_at' => null,
        ]);
        $printingVendorCategory = VendorCategory::withTrashed()->updateOrCreate(['code' => 'PRINTING'], [
            'name' => 'Printing Supplier',
            'description' => 'Printed material and publication supplier.',
            'is_active' => true,
            'deleted_at' => null,
        ]);

        ProcurementItem::withTrashed()->updateOrCreate(['code' => 'SVC-FACILITATION'], [
            'name' => 'Workshop Facilitation Service',
            'item_type' => 'service',
            'procurement_category_id' => $servicesCategory->id,
            'unit_of_measure_id' => $uomPkg->id,
            'is_active' => true,
            'deleted_at' => null,
        ]);
        ProcurementItem::withTrashed()->updateOrCreate(['code' => 'GOOD-PRINTING'], [
            'name' => 'Printed Information Material',
            'item_type' => 'goods',
            'procurement_category_id' => $goodsCategory->id,
            'unit_of_measure_id' => $uomPkg->id,
            'is_active' => true,
            'deleted_at' => null,
        ]);

        Vendor::updateOrCreate(['code' => 'VND-HOTEL-01'], [
            'name' => 'Hotel Santika Premiere Pontianak',
            'type' => 'company',
            'vendor_category_id' => $hotelVendorCategory->id,
            'npwp' => '02.456.789.1-701.000',
            'address' => 'Jl. Diponegoro No. 46, Pontianak',
            'contact_person' => 'Dewi Lestari (Sales Manager)',
            'phone' => '0561-737777',
            'email' => 'reservation@pontianak.santika.com',
            'bank_name' => 'Bank BCA',
            'bank_account_number' => '029-123456-7',
            'bank_account_holder' => 'PT Graha Santika Pontianak',
            'tax_id' => $ppn->id,
            'is_active' => true,
        ]);

        Vendor::updateOrCreate(['code' => 'VND-PRINT-02'], [
            'name' => 'Percetakan Media Grafika Kreasi',
            'type' => 'company',
            'vendor_category_id' => $printingVendorCategory->id,
            'npwp' => '01.987.654.3-403.000',
            'address' => 'Jl. Pajajaran No. 88, Bogor',
            'contact_person' => 'Bambang Irawan',
            'phone' => '0251-8345678',
            'email' => 'order@mediagrafika.co.id',
            'bank_name' => 'Bank Mandiri',
            'bank_account_number' => '133-00-9988776-5',
            'bank_account_holder' => 'PT Media Grafika Kreasi',
            'tax_id' => $pph23->id,
            'is_active' => true,
        ]);

        // 15. Expense Categories & Document Types
        ExpenseCategory::updateOrCreate(['code' => 'EXP-PERDIEM'], [
            'name' => 'Per Diem & Uang Saku Lapangan',
            'default_gl_account_id' => $createdCoa['5300']->id ?? null,
            'is_taxable' => false,
            'requires_receipt' => false,
            'requires_advance_settlement' => true,
            'is_active' => true,
        ]);

        ExpenseCategory::updateOrCreate(['code' => 'EXP-HOTEL'], [
            'name' => 'Penginapan / Hotel',
            'default_gl_account_id' => $createdCoa['5300']->id ?? null,
            'is_taxable' => false,
            'requires_receipt' => true,
            'requires_advance_settlement' => true,
            'is_active' => true,
        ]);

        ExpenseCategory::updateOrCreate(['code' => 'EXP-AIRFARE'], [
            'name' => 'Tiket Pesawat & Transport Antar Kota',
            'default_gl_account_id' => $createdCoa['5300']->id ?? null,
            'is_taxable' => false,
            'requires_receipt' => true,
            'requires_advance_settlement' => true,
            'is_active' => true,
        ]);

        ExpenseCategory::updateOrCreate(['code' => 'EXP-MEETING'], [
            'name' => 'Paket Meeting & Konsumsi Acara',
            'default_gl_account_id' => $createdCoa['5200']->id ?? null,
            'is_taxable' => true,
            'requires_receipt' => true,
            'requires_advance_settlement' => true,
            'is_active' => true,
        ]);

        DocumentType::updateOrCreate(['code' => 'DOC-RECEIPT'], [
            'name' => 'Kwitansi / Official Receipt',
            'description' => 'Kwitansi bertanda tangan basah / materai atau struk resmi',
            'is_mandatory_for_payout' => true,
            'is_active' => true,
        ]);

        DocumentType::updateOrCreate(['code' => 'DOC-TAX-INV'], [
            'name' => 'Faktur Pajak & Invoice Vendor',
            'description' => 'Faktur pajak elektronik dan tagihan resmi perusahaan',
            'is_mandatory_for_payout' => true,
            'is_active' => true,
        ]);

        DocumentType::updateOrCreate(['code' => 'DOC-TOR'], [
            'name' => 'Terms of Reference (TOR)',
            'description' => 'Kerangka Acuan Kerja kegiatan sebelum eksekusi',
            'is_mandatory_for_payout' => false,
            'is_active' => true,
        ]);

        DocumentType::updateOrCreate(['code' => 'DOC-BOARDING'], [
            'name' => 'Boarding Pass & Tiket',
            'description' => 'Bukti perjalanan fisik / e-boarding pass asli',
            'is_mandatory_for_payout' => true,
            'is_active' => true,
        ]);

        // 16. Asset Categories
        AssetCategory::updateOrCreate(['code' => 'ASSET-IT'], [
            'name' => 'Komputer, Laptop & Elektronik',
            'useful_life_months' => 48,
            'depreciation_method' => 'straight_line',
            'asset_gl_account_id' => $createdCoa['1210']->id ?? null,
            'depreciation_gl_account_id' => $createdCoa['5500']->id ?? null,
            'accumulated_gl_account_id' => $createdCoa['1290']->id ?? null,
            'is_active' => true,
        ]);

        AssetCategory::updateOrCreate(['code' => 'ASSET-VEHICLE'], [
            'name' => 'Kendaraan Operasional & Motor Lapangan',
            'useful_life_months' => 96,
            'depreciation_method' => 'straight_line',
            'asset_gl_account_id' => $createdCoa['1210']->id ?? null,
            'depreciation_gl_account_id' => $createdCoa['5500']->id ?? null,
            'accumulated_gl_account_id' => $createdCoa['1290']->id ?? null,
            'is_active' => true,
        ]);

        // 17. Approval Matrices (Tiered Multi-Level & Dynamic Rules)
        $roleDirector = Role::firstOrCreate(['slug' => 'executive-director'], ['name' => 'Executive Director']);
        $roleBoard = Role::firstOrCreate(['slug' => 'board-director'], ['name' => 'Board of Trustees']);
        $roleFinanceManager = Role::firstOrCreate(['slug' => 'finance-manager'], ['name' => 'Finance Manager']);
        $roleProjectManager = Role::firstOrCreate(['slug' => 'project-manager'], ['name' => 'Project Manager']);
        $roleFinanceOfficer = Role::firstOrCreate(['slug' => 'finance-officer'], ['name' => 'Finance Officer']);

        // EXPENSE: Tier 1 (Rp 0 - Rp 5 Jt) -> Level 1: Project Manager
        ApprovalMatrix::updateOrCreate([
            'module' => 'expense',
            'level' => 1,
            'min_amount' => 0,
            'max_amount' => 5000000,
        ], [
            'role_id' => $roleProjectManager->id,
            'approver_title' => 'Project Manager / Supervisor',
            'is_conditional_project_manager' => true,
            'description' => 'Biaya operasional & kegiatan lapangan rutin s.d Rp 5.000.000',
            'is_active' => true,
        ]);

        // EXPENSE: Tier 2 (Rp 5 Jt - Rp 25 Jt) -> Level 1: Project Manager, Level 2: Finance Manager
        ApprovalMatrix::updateOrCreate([
            'module' => 'expense',
            'level' => 2,
            'min_amount' => 5000001,
            'max_amount' => 25000000,
        ], [
            'role_id' => $roleFinanceManager->id,
            'approver_title' => 'Finance Manager',
            'is_conditional_project_manager' => false,
            'description' => 'Verifikasi kepatuhan anggaran dan cash flow oleh Finance Manager',
            'is_active' => true,
        ]);

        // EXPENSE: Tier 3 (Rp 25 Jt - Rp 100 Jt) -> Level 3: Executive Director
        ApprovalMatrix::updateOrCreate([
            'module' => 'expense',
            'level' => 3,
            'min_amount' => 25000001,
            'max_amount' => 100000000,
        ], [
            'role_id' => $roleDirector->id,
            'approver_title' => 'Executive Director',
            'is_conditional_project_manager' => false,
            'description' => 'Persetujuan pimpinan eksekutif untuk pengeluaran di atas Rp 25 Juta',
            'is_active' => true,
        ]);

        // EXPENSE: Tier 4 (> Rp 100 Jt) -> Level 4: Board of Trustees
        ApprovalMatrix::updateOrCreate([
            'module' => 'expense',
            'level' => 4,
            'min_amount' => 100000001,
            'max_amount' => null,
        ], [
            'role_id' => $roleBoard->id,
            'approver_title' => 'Board of Trustees / Dewan Pengawas',
            'is_conditional_project_manager' => false,
            'description' => 'Persetujuan Dewan Pengawas untuk pengeluaran strategis di atas Rp 100 Juta',
            'is_active' => true,
        ]);

        // CASH ADVANCE: Tier 1 (Rp 0 - Rp 10 Jt) -> Level 1: Project Manager
        ApprovalMatrix::updateOrCreate([
            'module' => 'cash_advance',
            'level' => 1,
            'min_amount' => 0,
            'max_amount' => 10000000,
        ], [
            'role_id' => $roleProjectManager->id,
            'approver_title' => 'Project Manager',
            'is_conditional_project_manager' => true,
            'description' => 'Uang muka kerja lapangan rutin s.d Rp 10 Juta',
            'is_active' => true,
        ]);

        // CASH ADVANCE: Tier 2 (Rp 10 Jt - Rp 50 Jt) -> Level 2: Finance Manager
        ApprovalMatrix::updateOrCreate([
            'module' => 'cash_advance',
            'level' => 2,
            'min_amount' => 10000001,
            'max_amount' => 50000000,
        ], [
            'role_id' => $roleFinanceManager->id,
            'approver_title' => 'Finance Manager',
            'is_conditional_project_manager' => false,
            'description' => 'Verifikasi likuiditas uang muka kerja oleh Finance Manager',
            'is_active' => true,
        ]);

        // CASH ADVANCE: Tier 3 (> Rp 50 Jt) -> Level 3: Executive Director
        ApprovalMatrix::updateOrCreate([
            'module' => 'cash_advance',
            'level' => 3,
            'min_amount' => 50000001,
            'max_amount' => null,
        ], [
            'role_id' => $roleDirector->id,
            'approver_title' => 'Executive Director',
            'is_conditional_project_manager' => false,
            'description' => 'Otorisasi direktur eksekutif untuk advance skala besar > Rp 50 Juta',
            'is_active' => true,
        ]);

        // PROCUREMENT: Tier 1 (Rp 0 - Rp 20 Jt) -> Level 1: Project Manager
        ApprovalMatrix::updateOrCreate([
            'module' => 'procurement',
            'level' => 1,
            'min_amount' => 0,
            'max_amount' => 20000000,
        ], [
            'role_id' => $roleProjectManager->id,
            'approver_title' => 'Procurement Officer & PM',
            'is_conditional_project_manager' => true,
            'description' => 'Pengadaan barang/jasa operasional kantor & logistik kegiatan',
            'is_active' => true,
        ]);

        // PROCUREMENT: Tier 2 (Rp 20 Jt - Rp 100 Jt) -> Level 2: Finance Manager
        ApprovalMatrix::updateOrCreate([
            'module' => 'procurement',
            'level' => 2,
            'min_amount' => 20000001,
            'max_amount' => 100000000,
        ], [
            'role_id' => $roleFinanceManager->id,
            'approver_title' => 'Finance Manager',
            'is_conditional_project_manager' => false,
            'description' => 'Evaluasi 3 perbandingan penawaran vendor & persetujuan PO',
            'is_active' => true,
        ]);

        // PROCUREMENT: Tier 3 (> Rp 100 Jt) -> Level 3: Executive Director
        ApprovalMatrix::updateOrCreate([
            'module' => 'procurement',
            'level' => 3,
            'min_amount' => 100000001,
            'max_amount' => null,
        ], [
            'role_id' => $roleDirector->id,
            'approver_title' => 'Executive Director',
            'is_conditional_project_manager' => false,
            'description' => 'Persetujuan kontrak pengadaan besar oleh Executive Director',
            'is_active' => true,
        ]);

        // DYNAMIC DONOR SPECIFIC RULE: Global Environment Facility (GEF)
        if (isset($donor1)) {
            ApprovalMatrix::updateOrCreate([
                'module' => 'expense',
                'donor_id' => $donor1->id,
                'level' => 2,
                'min_amount' => 50000000,
            ], [
                'role_id' => $roleDirector->id,
                'approver_title' => 'Executive Director & Donor Compliance Officer',
                'description' => 'Ketentuan khusus Donor GEF: Pengeluaran > Rp 50 Juta wajib audit kepatuhan donor',
                'is_active' => true,
            ]);
        }

        // DYNAMIC PROJECT SPECIFIC RULE: Community Forest Monitoring (PRJ-PTK-01)
        if (isset($prj1)) {
            ApprovalMatrix::updateOrCreate([
                'module' => 'budget_reallocation',
                'project_id' => $prj1->id,
                'level' => 1,
                'min_amount' => 0,
            ], [
                'role_id' => $roleProjectManager->id,
                'approver_title' => 'Project Manager (Community Forest)',
                'description' => 'Realokasi anggaran antar pos biaya Project Community Forest Monitoring',
                'is_active' => true,
            ]);
        }

        // 18. Real Database Transactions for Over-Budget & Warning Alerts
        $blFord1 = BudgetLine::where('line_code', 'BL-FORD-2.1')->first();
        $blFord2 = BudgetLine::where('line_code', 'BL-FORD-3.1')->first();

        if ($blFord1) {
            $j1 = Journal::updateOrCreate(['journal_number' => 'JV-2026-SEED-01'], [
                'journal_date' => now()->subDays(5)->toDateString(),
                'journal_type' => 'manual',
                'reference' => 'EXP-WORKSHOP-001',
                'description' => 'Realisasi Workshop & Pelatihan Tata Kelola Hutan (Over-Budget)',
                'currency_id' => $idr->id,
                'status' => 'posted',
                'posted_at' => now()->subDays(5),
            ]);

            JournalLine::updateOrCreate([
                'journal_id' => $j1->id,
                'budget_line_id' => $blFord1->id,
                'line_order' => 1,
            ], [
                'account_id' => $createdCoa['11410']->id ?? ChartOfAccount::first()->id,
                'donor_id' => $grantFord->donor_id ?? null,
                'project_id' => $blFord1->project_id,
                'line_description' => 'Biaya Konsumsi, Akomodasi & Paket Training',
                'debit' => 22500000.00,
                'credit' => 0,
            ]);

            JournalLine::updateOrCreate([
                'journal_id' => $j1->id,
                'line_order' => 2,
            ], [
                'account_id' => $createdCoa['10210']->id ?? ChartOfAccount::first()->id,
                'line_description' => 'Pembayaran via BNI Sekretariat 1',
                'debit' => 0,
                'credit' => 22500000.00,
            ]);
        }

        if ($blFord2) {
            BudgetCommitment::updateOrCreate([
                'reference' => 'PO-2026-SEED-02',
                'budget_line_id' => $blFord2->id,
            ], [
                'source_type' => 'purchase_order',
                'source_id' => 1,
                'amount' => 8200000.00,
                'status' => 'open',
            ]);
        }

        // 19. System Notifications
        Notification::updateOrCreate([
            'title' => 'Pending Approval: Expense Claim #EXP-2026-004',
        ], [
            'message' => 'Pengajuan klaim per diem & akomodasi lapangan Pontianak membutuhkan verifikasi Finance Manager.',
            'type' => 'approval',
            'action_url' => '/expenses-approvals/approvals',
            'created_at' => now()->subMinutes(15),
        ]);

        Notification::updateOrCreate([
            'title' => 'Over-Budget Warning: Grant BL-FORD-2.1',
        ], [
            'message' => 'Pos biaya Direct Activity Costs (BL-FORD-2.1) telah melebihi batas anggaran yang disetujui.',
            'type' => 'alert',
            'action_url' => '/funding-projects/budget',
            'created_at' => now()->subHours(2),
        ]);

        Notification::updateOrCreate([
            'title' => 'Purchase Request Approved: PR-2026-001',
        ], [
            'message' => 'Permintaan pengadaan peralatan komputer lapangan telah disetujui oleh Executive Director.',
            'type' => 'success',
            'action_url' => '/procurement/purchase-requests',
            'created_at' => now()->subHours(5),
        ]);

        Notification::updateOrCreate([
            'title' => 'Monthly Tax Report Reminder',
        ], [
            'message' => 'Laporan PPh 21/23 untuk bulan ini siap diverifikasi dan dikirim.',
            'type' => 'info',
            'action_url' => '/accounting/tax',
            'created_at' => now()->subDay(),
        ]);
    }
}
