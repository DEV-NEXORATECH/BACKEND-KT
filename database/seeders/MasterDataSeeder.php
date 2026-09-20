<?php

namespace Database\Seeders;

use App\Models\Master\Activity;
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
use App\Models\Master\ReportingDimension;
use App\Models\Master\Tax;
use App\Models\Master\UnitOfMeasure;
use App\Models\Master\Vendor;
use App\Models\Role;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Currencies (Base foundation)
        $idr = Currency::updateOrCreate(['code' => 'IDR'], [
            'name' => 'Indonesian Rupiah',
            'symbol' => 'Rp',
            'decimal_places' => 0,
            'is_base_currency' => true,
            'is_active' => true,
        ]);

        $usd = Currency::updateOrCreate(['code' => 'USD'], [
            'name' => 'United States Dollar',
            'symbol' => '$',
            'decimal_places' => 2,
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

        // Exchange Rates
        ExchangeRate::updateOrCreate([
            'date' => now()->toDateString(),
            'from_currency_id' => $usd->id,
            'to_currency_id' => $idr->id,
        ], [
            'rate' => 16250.000000,
            'source' => 'Bank Indonesia JISDOR',
            'is_active' => true,
        ]);

        ExchangeRate::updateOrCreate([
            'date' => now()->toDateString(),
            'from_currency_id' => $eur->id,
            'to_currency_id' => $idr->id,
        ], [
            'rate' => 17800.000000,
            'source' => 'Bank Indonesia JISDOR',
            'is_active' => true,
        ]);

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

        $deptProg = Department::updateOrCreate(['code' => 'DEPT-PROG'], [
            'organization_id' => $org->id,
            'name' => 'Program & Operational',
            'manager_name' => 'Denny Bhatara',
            'is_active' => true,
        ]);

        $deptFin = Department::updateOrCreate(['code' => 'DEPT-FIN'], [
            'organization_id' => $org->id,
            'name' => 'Finance & Administration',
            'manager_name' => 'Syaifani Auliana Havid',
            'is_active' => true,
        ]);

        $deptComms = Department::updateOrCreate(['code' => 'DEPT-COMMS'], [
            'organization_id' => $org->id,
            'name' => 'Communications',
            'manager_name' => 'Sarah Rosemery Megumi',
            'is_active' => true,
        ]);

        $deptCamp = Department::updateOrCreate(['code' => 'DEPT-CAMP'], [
            'organization_id' => $org->id,
            'name' => 'Campaigner',
            'manager_name' => 'Abil Achmad Akbar',
            'is_active' => true,
        ]);

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

        // 4. Chart of Accounts (Standard NGO)
        $coaList = [
            ['code' => '1000', 'name' => 'ASSETS', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 1, 'is_header' => true],
            ['code' => '1100', 'name' => 'Cash & Cash Equivalents', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 2, 'is_header' => true, 'parent_code' => '1000'],
            ['code' => '1110', 'name' => 'Kas Kecil Bogor', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 3, 'is_header' => false, 'parent_code' => '1100'],
            ['code' => '1120', 'name' => 'Bank Mandiri IDR Operational', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 3, 'is_header' => false, 'parent_code' => '1100'],
            ['code' => '1121', 'name' => 'Bank BCA USD Grant Account', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 3, 'is_header' => false, 'parent_code' => '1100'],
            ['code' => '1130', 'name' => 'Uang Muka Kerja / Staff Advance', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 3, 'is_header' => false, 'parent_code' => '1100'],
            ['code' => '1200', 'name' => 'Fixed Assets', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 2, 'is_header' => true, 'parent_code' => '1000'],
            ['code' => '1210', 'name' => 'Peralatan Kantor & Komputer', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 3, 'is_header' => false, 'parent_code' => '1200'],
            ['code' => '1290', 'name' => 'Akumulasi Penyusutan Peralatan', 'account_type' => 'asset', 'normal_balance' => 'credit', 'level' => 3, 'is_header' => false, 'parent_code' => '1200'],

            ['code' => '2000', 'name' => 'LIABILITIES', 'account_type' => 'liability', 'normal_balance' => 'credit', 'level' => 1, 'is_header' => true],
            ['code' => '2100', 'name' => 'Current Liabilities', 'account_type' => 'liability', 'normal_balance' => 'credit', 'level' => 2, 'is_header' => true, 'parent_code' => '2000'],
            ['code' => '2110', 'name' => 'Hutang Usaha / Accounts Payable', 'account_type' => 'liability', 'normal_balance' => 'credit', 'level' => 3, 'is_header' => false, 'parent_code' => '2100'],
            ['code' => '2120', 'name' => 'Hutang Pajak PPh 21/23', 'account_type' => 'liability', 'normal_balance' => 'credit', 'level' => 3, 'is_header' => false, 'parent_code' => '2100'],

            ['code' => '3000', 'name' => 'NET ASSETS / FUND BALANCE', 'account_type' => 'equity', 'normal_balance' => 'credit', 'level' => 1, 'is_header' => true],
            ['code' => '3100', 'name' => 'Unrestricted Net Assets', 'account_type' => 'equity', 'normal_balance' => 'credit', 'level' => 2, 'is_header' => false, 'parent_code' => '3000'],
            ['code' => '3200', 'name' => 'Temporarily Restricted Net Assets', 'account_type' => 'equity', 'normal_balance' => 'credit', 'level' => 2, 'is_header' => false, 'parent_code' => '3000'],

            ['code' => '4000', 'name' => 'GRANT REVENUE & FUNDING', 'account_type' => 'revenue', 'normal_balance' => 'credit', 'level' => 1, 'is_header' => true],
            ['code' => '4100', 'name' => 'Grant Revenue - Institutional Donors', 'account_type' => 'revenue', 'normal_balance' => 'credit', 'level' => 2, 'is_header' => false, 'parent_code' => '4000'],
            ['code' => '4200', 'name' => 'Donasi Publik & Lainnya', 'account_type' => 'revenue', 'normal_balance' => 'credit', 'level' => 2, 'is_header' => false, 'parent_code' => '4000'],

            ['code' => '5000', 'name' => 'PROGRAM & OPERATIONAL EXPENSES', 'account_type' => 'expense', 'normal_balance' => 'debit', 'level' => 1, 'is_header' => true],
            ['code' => '5100', 'name' => 'Personnel & Staff Expenses', 'account_type' => 'expense', 'normal_balance' => 'debit', 'level' => 2, 'is_header' => false, 'parent_code' => '5000'],
            ['code' => '5200', 'name' => 'Direct Activity & Workshop Expenses', 'account_type' => 'expense', 'normal_balance' => 'debit', 'level' => 2, 'is_header' => false, 'parent_code' => '5000'],
            ['code' => '5300', 'name' => 'Travel, Per Diem & Accommodation', 'account_type' => 'expense', 'normal_balance' => 'debit', 'level' => 2, 'is_header' => false, 'parent_code' => '5000'],
            ['code' => '5400', 'name' => 'Office Rent & Utilities', 'account_type' => 'expense', 'normal_balance' => 'debit', 'level' => 2, 'is_header' => false, 'parent_code' => '5000'],
            ['code' => '5500', 'name' => 'Beban Penyusutan Aset', 'account_type' => 'expense', 'normal_balance' => 'debit', 'level' => 2, 'is_header' => false, 'parent_code' => '5000'],
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
        $ppn = Tax::updateOrCreate(['code' => 'PPN-11'], [
            'name' => 'Pajak Pertambahan Nilai 11%',
            'tax_type' => 'PPN',
            'rate_percent' => 11.00,
            'is_active' => true,
        ]);

        $pph21 = Tax::updateOrCreate(['code' => 'PPH-21'], [
            'name' => 'PPh Pasal 21 Tenaga Ahli / Konsultan',
            'tax_type' => 'PPH21',
            'rate_percent' => 5.00,
            'purchase_gl_account_id' => $createdCoa['2120']->id ?? null,
            'is_active' => true,
        ]);

        $pph23 = Tax::updateOrCreate(['code' => 'PPH-23'], [
            'name' => 'PPh Pasal 23 Jasa & Sewa',
            'tax_type' => 'PPH23',
            'rate_percent' => 2.00,
            'purchase_gl_account_id' => $createdCoa['2120']->id ?? null,
            'is_active' => true,
        ]);

        // 6. Bank Accounts & Petty Cash
        $bankMandiri = BankAccount::updateOrCreate(['account_number' => '133-00-1234567-8'], [
            'organization_id' => $org->id,
            'bank_name' => 'Bank Mandiri',
            'account_name' => 'Perkumpulan Kaoem Telapak',
            'swift_code' => 'BMRIIDJA',
            'currency_id' => $idr->id,
            'gl_account_id' => $createdCoa['1120']->id ?? null,
            'is_active' => true,
        ]);

        $bankBcaUsd = BankAccount::updateOrCreate(['account_number' => '889-01-987654-3'], [
            'organization_id' => $org->id,
            'bank_name' => 'Bank BCA USD',
            'account_name' => 'Perkumpulan Kaoem Telapak - Grant Pool',
            'swift_code' => 'CENAIDJA',
            'currency_id' => $usd->id,
            'gl_account_id' => $createdCoa['1121']->id ?? null,
            'is_active' => true,
        ]);

        PettyCash::updateOrCreate(['code' => 'PC-BGR'], [
            'organization_id' => $org->id,
            'office_location_id' => $headOffice->id,
            'name' => 'Kas Kecil Sekretariat Bogor',
            'custodian_name' => 'Dina Amalia (Kasir)',
            'currency_id' => $idr->id,
            'limit_amount' => 10000000,
            'gl_account_id' => $createdCoa['1110']->id ?? null,
            'is_active' => true,
        ]);

        // 7. Payment Methods
        PaymentMethod::updateOrCreate(['code' => 'PM-BT'], [
            'name' => 'Bank Transfer (Mandiri/BCA)',
            'type' => 'bank_transfer',
            'default_gl_account_id' => $createdCoa['1120']->id ?? null,
            'is_active' => true,
        ]);

        PaymentMethod::updateOrCreate(['code' => 'PM-PC'], [
            'name' => 'Petty Cash Payout',
            'type' => 'petty_cash',
            'default_gl_account_id' => $createdCoa['1110']->id ?? null,
            'is_active' => true,
        ]);

        PaymentMethod::updateOrCreate(['code' => 'PM-CARD'], [
            'name' => 'Corporate Credit Card',
            'type' => 'corporate_card',
            'default_gl_account_id' => $createdCoa['2110']->id ?? null,
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
        $grantFord = GrantAgreement::updateOrCreate(['grant_no' => 'GRT-2026-FORD-01'], [
            'donor_id' => $donorFord->id,
            'funding_source_id' => $fundFoundation->id,
            'agreement_name' => 'Strengthening Indigenous Rights and Forest Governance in Borneo',
            'start_date' => '2026-01-01',
            'end_date' => '2027-12-31',
            'currency_id' => $usd->id,
            'grant_value' => 350000.00,
            'exchange_rate_contract' => 16000.000000,
            'bank_account_id' => $bankBcaUsd->id,
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
            'bank_account_id' => $bankBcaUsd->id,
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
        $uomTrip = UnitOfMeasure::updateOrCreate(['code' => 'TRIP'], [
            'name' => 'Round Trip',
            'category' => 'Travel',
            'is_active' => true,
        ]);

        $uomMo = UnitOfMeasure::updateOrCreate(['code' => 'MONTH'], [
            'name' => 'Person-Month',
            'category' => 'Time',
            'is_active' => true,
        ]);

        $uomPkg = UnitOfMeasure::updateOrCreate(['code' => 'PKG'], [
            'name' => 'Package / Paket',
            'category' => 'Quantity',
            'is_active' => true,
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
                'office_location_id' => $headOffice->id,
                'position' => 'Finance Staff',
                'bank_name' => 'BCA',
                'bank_account_number' => '1234567890',
                'bank_account_holder' => 'Siti Aminah',
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
            ],
            [
                'employee_id_number' => 'ID00037',
                'name' => 'WINDA ASTUTI',
                'email' => 'Winda.astuti@kaoemtelapak.org',
                'department_id' => $deptFin->id,
                'office_location_id' => $headOffice->id,
                'position' => 'Finance Officer',
                'bank_name' => 'BNI',
                'bank_account_number' => '003624997783',
                'bank_account_holder' => 'Winda Astuti',
            ],
            [
                'employee_id_number' => 'EMP-TBC-01',
                'name' => 'KWEE VIENA LESTARI TANJUNG',
                'email' => 'viena.tanjung@kaoemtelapak.org',
                'department_id' => $deptCamp->id,
                'office_location_id' => $headOffice->id,
                'position' => 'Sr. Campaigner',
                'bank_name' => 'TBC',
                'bank_account_number' => 'TBC',
                'bank_account_holder' => 'Kwee Viena Lestari Tanjung',
            ],
            [
                'employee_id_number' => 'EMP-TBC-02',
                'name' => 'Senior Campaigner (TBC)',
                'email' => 'campaigner.tbc@kaoemtelapak.org',
                'department_id' => $deptCamp->id,
                'office_location_id' => $headOffice->id,
                'position' => 'Sr. Campaigner',
                'bank_name' => 'TBC',
                'bank_account_number' => 'TBC',
                'bank_account_holder' => 'TBC',
            ],
        ];

        foreach ($employeesList as $empData) {
            Employee::updateOrCreate(
                ['employee_id_number' => $empData['employee_id_number']],
                [...$empData, 'is_active' => true]
            );
        }

        Vendor::updateOrCreate(['code' => 'VND-HOTEL-01'], [
            'name' => 'Hotel Santika Premiere Pontianak',
            'type' => 'company',
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
    }
}