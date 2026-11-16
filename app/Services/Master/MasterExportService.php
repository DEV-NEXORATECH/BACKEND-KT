<?php

namespace App\Services\Master;

use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MasterExportService
{
    public function attachment(string $entityName, $collection, string $format): array
    {
        $format = strtolower($format);
        $format = $format === 'google_sheets' ? 'csv' : $format;
        $safeName = strtolower(preg_replace('/[^A-Za-z0-9_-]/', '-', $entityName));
        $base = tempnam(sys_get_temp_dir(), 'kt-mail-');

        if ($format === 'pdf') {
            $path = $base . '.pdf';
            file_put_contents($path, Pdf::loadView('exports.master.template', [
                'entityName' => $entityName,
                'collection' => $collection,
                'timestamp' => now()->translatedFormat('d F Y H:i:s'),
            ])->setPaper('a4', 'landscape')->output());
            @unlink($base);
            return [$path, "{$safeName}.pdf", 'application/pdf'];
        }

        if (in_array($format, ['xlsx', 'excel'], true)) {
            $path = $base . '.xlsx';
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $rows = [];
            if ($collection->isNotEmpty()) {
                $first = $this->flattenArray($collection->first()->toArray());
                $rows[] = array_map(fn (string $key): string => $this->displayHeader($key), array_keys($first));
                foreach ($collection as $item) $rows[] = array_values($this->flattenArray($item->toArray()));
            } else $rows[] = ['No data'];
            $sheet->fromArray($rows, null, 'A1');
            $writer = new Xlsx($spreadsheet);
            $writer->save($path);
            @unlink($base);
            return [$path, "{$safeName}.xlsx", 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        }

        $path = $base . '.csv';
        $handle = fopen($path, 'w');
        fprintf($handle, "\xEF\xBB\xBF");
        if ($collection->isNotEmpty()) {
            $first = $this->flattenArray($collection->first()->toArray());
            fputcsv($handle, array_map(fn (string $key): string => $this->displayHeader($key), array_keys($first)));
            foreach ($collection as $item) fputcsv($handle, array_values($this->flattenArray($item->toArray())));
        } else fputcsv($handle, ['No data']);
        fclose($handle);
        @unlink($base);
        return [$path, "{$safeName}.csv", 'text/csv'];
    }
    public function template(string $entityName, array $columns, array $headerOverrides = [])
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Input Data');
        $displayColumns = $this->templateHeaders($columns, $headerOverrides);
        $isCoaTemplate = $entityName === 'ChartOfAccount';
        $isBankTemplate = $entityName === 'BankAccount';
        if ($isBankTemplate) $displayColumns[] = 'Import Note';
        $sheet->fromArray([$displayColumns], null, 'A1');
        if ($isCoaTemplate) {
            $coaTemplateRows = [
                ['10000', 'BANK AND CASH', 'Bank', 'Current'],
                ['10100', 'BANK AND CASH:CASH', 'Bank', 'Current'],
                ['10110', 'BANK AND CASH:CASH:Petty Cash', 'Bank', 'Current'],
                ['10120', 'BANK AND CASH:CASH:Cash on Hand', 'Bank', 'Current'],
                ['10200', 'BANK AND CASH:BANK BNI', 'Bank', 'Current'],
                ['10210', 'BANK AND CASH:BANK BNI:BNI Sekretariat 1 - 460924325', 'Bank', 'Current'],
                ['10212', 'BANK AND CASH:BANK BNI:BNI Norad EIA - 737971179', 'Bank', 'Current'],
                ['10213', 'BANK AND CASH:BANK BNI:BNI Norad Fern - 7379666219', 'Bank', 'Current'],
                ['10214', 'BANK AND CASH:BANK BNI:BNI Norad Samdhana - 628472846', 'Bank', 'Current'],
                ['10215', 'BANK AND CASH:BANK BNI:BNI Packard - 583418434', 'Bank', 'Current'],
                ['10216', 'BANK AND CASH:BANK BNI:BNI TET - 460924700', 'Bank', 'Current'],
                ['10217', 'BANK AND CASH:BANK BNI:BNI FGMC EIA - 1821056816', 'Bank', 'Current'],
                ['10218', 'BANK AND CASH:BANK BNI:BNI USAID - 182156678', 'Bank', 'Current'],
                ['10219', 'BANK AND CASH:BANK BNI:BNI Waterloo - 1785559365', 'Bank', 'Current'],
                ['10220', 'BANK AND CASH:BANK BNI:BNI Sekretariat 3 / Tifa Foundation - 460924700', 'Bank', 'Current'],
                ['10221', 'BANK AND CASH:BANK BNI:BNI Sekretariat 4 - 737966602', 'Bank', 'Current'],
                ['10222', 'BANK AND CASH:BANK BNI:BNI Sekretariat 5 - 1785559592', 'Bank', 'Current'],
                ['10223', 'BANK AND CASH:BANK BNI:BNI Sekretariat 2 - 1821056678', 'Bank', 'Current'],
                ['10224', 'BANK AND CASH:BANK BNI:BNI FCDO UK Embassy - 583418434', 'Bank', 'Current'],
                ['10225', 'BANK AND CASH:BANK BNI:BNI FGMC EIA 2 - 1821056816', 'Bank', 'Current'],
                ['10226', 'BANK AND CASH:BANK BNI:BNI Waterloo 2 - 1785559365', 'Bank', 'Current'],
                ['10227', 'BANK AND CASH:BANK BNI:BNI Tifa Foundation - 460924700', 'Bank', 'Current'],
                ['10228', 'BANK AND CASH:BANK BNI:BNI RUU MA -  628472846', 'Bank', 'Current'],
                ['10229', 'BANK AND CASH:BANK BNI:BNI Rakawuta & Muara Tae - 7379666219', 'Bank', 'Current'],
                ['10300', 'BANK AND CASH:BANK CIMB NIAGA', 'Bank', 'Current'],
                ['10310', 'BANK AND CASH:BANK CIMB NIAGA:CIMB Niaga Sekretariat - 800193159700', 'Bank', 'Current'],
                ['10400', 'BANK AND CASH:Bilyet/Giro BNI (Deposito)', 'Bank', 'Current'],
                ['10500', 'BANK AND CASH:Bilyet/Giro Cimbniaga (Deposito)', 'Bank', 'Current'],
                ['11000', 'ACCOUNTS RECEIVABLE (A/R)', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['11400', 'ACCOUNTS RECEIVABLE (A/R):AR PROJECT', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['11410', 'ACCOUNTS RECEIVABLE (A/R):AR PROJECT:AR FGMC EIA', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['11411', 'ACCOUNTS RECEIVABLE (A/R):AR PROJECT:AR MONTPELLIER', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['11412', 'ACCOUNTS RECEIVABLE (A/R):AR PROJECT:AR USAID', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['11413', 'ACCOUNTS RECEIVABLE (A/R):AR PROJECT:AR NORAD FERN', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['11414', 'ACCOUNTS RECEIVABLE (A/R):AR PROJECT:AR Norad Samdhana', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['11415', 'ACCOUNTS RECEIVABLE (A/R):AR PROJECT:AR Sekretariat', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['11416', 'ACCOUNTS RECEIVABLE (A/R):AR PROJECT:AR FCDO - UK Embassy', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['11417', 'ACCOUNTS RECEIVABLE (A/R):AR PROJECT:AR NORAD EIA', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['11418', 'ACCOUNTS RECEIVABLE (A/R):AR PROJECT:AR Waterloo', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['11500', 'ACCOUNTS RECEIVABLE (A/R):LOAN STAFF', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['11510', 'ACCOUNTS RECEIVABLE (A/R):LOAN STAFF:Abu Hasan Meridian', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['11511', 'ACCOUNTS RECEIVABLE (A/R):LOAN STAFF:Freska Hanakin', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['11512', 'ACCOUNTS RECEIVABLE (A/R):LOAN STAFF:Munip', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['11513', 'ACCOUNTS RECEIVABLE (A/R):LOAN STAFF:Olvy O Tumbelaka', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['11514', 'ACCOUNTS RECEIVABLE (A/R):LOAN STAFF:Andi Gustaf Lekto', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['11515', 'ACCOUNTS RECEIVABLE (A/R):LOAN STAFF:Sarah Megumi', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['11516', 'ACCOUNTS RECEIVABLE (A/R):LOAN STAFF:Teguh Iman Affendi', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['11517', 'ACCOUNTS RECEIVABLE (A/R):LOAN STAFF:Syaifani Auliana Havid', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['11518', 'ACCOUNTS RECEIVABLE (A/R):LOAN STAFF:Indra Nuwata', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['11519', 'ACCOUNTS RECEIVABLE (A/R):LOAN STAFF:Denny Bhatara', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['11520', 'ACCOUNTS RECEIVABLE (A/R):LOAN STAFF:Veni Siregar', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['11521', 'ACCOUNTS RECEIVABLE (A/R):LOAN STAFF:Renaldi Sastra', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['11522', 'ACCOUNTS RECEIVABLE (A/R):LOAN STAFF:Winda Apriyani', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['11523', 'ACCOUNTS RECEIVABLE (A/R):LOAN STAFF:Riki Suryandi', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['11524', 'ACCOUNTS RECEIVABLE (A/R):LOAN STAFF:Abil Achmad Akbar', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['11525', 'ACCOUNTS RECEIVABLE (A/R):LOAN STAFF:Rika Putri Andini', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['11526', 'ACCOUNTS RECEIVABLE (A/R):LOAN STAFF:Ayu Perwitosari', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['12000', 'ACCOUNTS RECEIVABLE (A/R):LOAN MEMBER/ANGGOTA', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['12100', 'ACCOUNTS RECEIVABLE (A/R):LOAN MEMBER/ANGGOTA:Abdon Nababan', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['16000', 'Sub-Grantee', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['16100', 'Sub-Grantee:Kaharingan Institute', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['16200', 'Sub-Grantee:PLHL', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['16300', 'Sub-Grantee:Link-AR Borneo', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['16400', 'Sub-Grantee:AMAN INHU', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['16500', 'Sub-Grantee:WALHI', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['16600', 'Sub-Grantee:KOMNAS DESA SULTRA', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['16700', 'Sub-Grantee:INHIDES', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['16800', 'Sub-Grantee:LSM Green Of Borneo', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['16900', 'Sub-Grantee:SPKS Sekadau', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['16910', 'Sub-Grantee:Perkumpulan Fakawele', 'Accounts receivable (A/R)', 'Accounts Receivable (A/R)'],
                ['13000', 'Prepaid Expense', 'Other Current Assets', 'Other current assets'],
                ['14000', 'In-transit Funds', 'Other Current Assets', 'Undeposited Funds'],
                ['15000', 'STAFF CASH ADVANCE', 'Other Current Assets', 'Employee Cash Advances'],
                ['15100', 'STAFF CASH ADVANCE:CA - Abu Hasan Meridian', 'Other Current Assets', 'Employee Cash Advances'],
                ['15110', 'STAFF CASH ADVANCE:CA - Sarah Megumi', 'Other Current Assets', 'Employee Cash Advances'],
                ['15120', 'STAFF CASH ADVANCE:CA - Patria Rizky', 'Other Current Assets', 'Employee Cash Advances'],
                ['15130', 'STAFF CASH ADVANCE:CA - Veni Siregar', 'Other Current Assets', 'Employee Cash Advances'],
                ['15140', 'STAFF CASH ADVANCE:CA - M. Khalid', 'Other Current Assets', 'Employee Cash Advances'],
                ['15200', 'STAFF CASH ADVANCE:CA - Denny Bhatara', 'Other Current Assets', 'Employee Cash Advances'],
                ['15300', 'STAFF CASH ADVANCE:CA - Ziadatunnisa', 'Other Current Assets', 'Employee Cash Advances'],
                ['15400', 'STAFF CASH ADVANCE:CA -  Agetha Tri Lestari', 'Other Current Assets', 'Employee Cash Advances'],
                ['15500', 'STAFF CASH ADVANCE:CA - Munip', 'Other Current Assets', 'Employee Cash Advances'],
                ['15600', 'STAFF CASH ADVANCE:CA - Rika Putri Andini', 'Other Current Assets', 'Employee Cash Advances'],
                ['15700', 'STAFF CASH ADVANCE:CA - Riki Suryandi', 'Other Current Assets', 'Employee Cash Advances'],
                ['15800', 'STAFF CASH ADVANCE:CA - Zufar Fauzan', 'Other Current Assets', 'Employee Cash Advances'],
                ['15900', 'STAFF CASH ADVANCE:CA - Pardi', 'Other Current Assets', 'Employee Cash Advances'],
                ['15910', 'STAFF CASH ADVANCE:CA - Abil Achmad Akbar', 'Other Current Assets', 'Employee Cash Advances'],
                ['15920', 'STAFF CASH ADVANCE:CA Olvy Octavianita T', 'Other Current Assets', 'Employee Cash Advances'],
                ['15921', 'STAFF CASH ADVANCE:CA Renaldi Sastra', 'Other Current Assets', 'Employee Cash Advances'],
                ['15922', 'STAFF CASH ADVANCE:CA - Jihan Faatihah', 'Other Current Assets', 'Employee Cash Advances'],
                ['15923', 'STAFF CASH ADVANCE:CA - Febry Ronaldo', 'Other Current Assets', 'Employee Cash Advances'],
                ['15924', 'STAFF CASH ADVANCE:CA - Syaifani A Havid', 'Other Current Assets', 'Employee Cash Advances'],
                ['15925', 'STAFF CASH ADVANCE:CA - Arif Candra', 'Other Current Assets', 'Employee Cash Advances'],
                ['15926', 'STAFF CASH ADVANCE:CA - Ayu Perwitosari', 'Other Current Assets', 'Employee Cash Advances'],
                ['15927', 'STAFF CASH ADVANCE:CA - M Irbah', 'Other Current Assets', 'Employee Cash Advances'],
                ['15928', 'STAFF CASH ADVANCE:CA - Elisabeth C Giay', 'Other Current Assets', 'Employee Cash Advances'],
                ['15929', 'STAFF CASH ADVANCE:CA - Leorana Sihotang', 'Other Current Assets', 'Employee Cash Advances'],
                ['17000', 'CA Consultant/Others', 'Other Current Assets', 'Employee Cash Advances'],
                ['17100', 'CA Consultant/Others:CA Setara Jambi', 'Other Current Assets', 'Employee Cash Advances'],
                ['17200', 'CA Consultant/Others:CA Anggota/Internal Program KT', 'Other Current Assets', 'Employee Cash Advances'],
                ['17300', 'CA Consultant/Others:CA Consultant', 'Other Current Assets', 'Employee Cash Advances'],
                ['18000', 'Fixed Assets', 'Fixed Assets', 'Machinery and equipment'],
                ['18100', 'Fixed Assets:Laptop', 'Fixed Assets', 'Other fixed assets'],
                ['18100-1', 'Fixed Assets:Laptop:Acc. Depr. Laptop', 'Fixed Assets', 'Accumulated depreciation on property, plant and equipment'],
                ['18200', 'Fixed Assets:Printer', 'Fixed Assets', 'Machinery and equipment'],
                ['18200-1', 'Fixed Assets:Printer:Acc. Depr. Printer', 'Fixed Assets', 'Accumulated depreciation on property, plant and equipment'],
                ['18300', 'Fixed Assets:Handphone', 'Fixed Assets', 'Other fixed assets'],
                ['18300-1', 'Fixed Assets:Handphone:Acc. Depr. Handphone', 'Fixed Assets', 'Accumulated depreciation on property, plant and equipment'],
                ['18400', 'Fixed Assets:Accessories / Other Equipment', 'Fixed Assets', 'Other fixed assets'],
                ['18400-1', 'Fixed Assets:Accessories / Other Equipment:Acc. Depr. Accessoriess / Other Equipment', 'Fixed Assets', 'Accumulated depreciation on property, plant and equipment'],
                ['20000', 'ACCOUNT PAYABLE', 'Accounts payable (A/P)', 'Accounts Payable (A/P)'],
                ['20100', 'ACCOUNT PAYABLE:Accrued Payment', 'Accounts payable (A/P)', 'Accounts Payable (A/P)'],
                ['20200', 'ACCOUNT PAYABLE:Inter-Project Payable', 'Accounts payable (A/P)', 'Accounts Payable (A/P)'],
                ['20300', 'ACCOUNT PAYABLE:Tax Payable (PPh 21)', 'Accounts payable (A/P)', 'Accounts Payable (A/P)'],
                ['20400', 'ACCOUNT PAYABLE:Tax Payable (PPh 23)', 'Accounts payable (A/P)', 'Accounts Payable (A/P)'],
                ['20500', 'ACCOUNT PAYABLE:Tax Payable (PPh Final Pasal 4 ayat 2)', 'Accounts payable (A/P)', 'Accounts Payable (A/P)'],
                ['22000', 'Liabilitas Imbalan Pasca Kerja', 'Long Term Liabilities', 'Other non-current liabilities'],
                ['30000', 'OPENING BALANCE EQUITY', 'Equity', 'Opening Balance Equity'],
                ['32000', 'RETAINED EARNINGS', 'Equity', 'Retained Earnings'],
                ['40000', 'REVENUE', 'Income', 'Revenue - General'],
                ['40100', 'REVENUE:Grants Revenue', 'Income', 'Non-Profit Income'],
                ['40200', 'REVENUE:Donations', 'Income', 'Non-Profit Income'],
                ['40300', 'REVENUE:Member Dues', 'Income', 'Other Primary Income'],
                ['40400', 'REVENUE:Project Contribution', 'Income', 'Non-Profit Income'],
                ['40500', 'REVENUE:Deposito Interest', 'Income', 'Service/Fee Income'],
                ['40600', 'REVENUE:Fixed Deposit', 'Income', 'Service/Fee Income'],
                ['40700', 'REVENUE:Others Income', 'Income', 'Non-Profit Income'],
                ['50000', 'EXPENSES', 'Expenses', 'Office/General Administrative Expenses'],
                ['51000', 'EXPENSES:Personnel & Consultacy', 'Expenses', 'Cost of Labour'],
                ['51100', 'EXPENSES:Personnel & Consultacy:Salary', 'Expenses', 'Cost of Labour'],
                ['51200', 'EXPENSES:Personnel & Consultacy:Benefit', 'Expenses', 'Commissions and fees'],
                ['51300', 'EXPENSES:Personnel & Consultacy:Consultant Fee', 'Expenses', 'Legal and professional fees'],
                ['51400', 'EXPENSES:Personnel & Consultacy:Sub-Grant', 'Expenses', 'Meals and entertainment'],
                ['51500', 'EXPENSES:Personnel & Consultacy:Staff and consultants', 'Expenses', 'Legal and professional fees'],
                ['51600', 'EXPENSES:Personnel & Consultacy:Post Employment Benefit Expense', 'Expenses', 'Cost of Labour'],
                ['52000', 'EXPENSES:Event, Ads & Equipment', 'Expenses', 'Meals and entertainment'],
                ['52100', 'EXPENSES:Event, Ads & Equipment:Ads - Digital and Non Digital Media', 'Expenses', 'Advertising/Promotional'],
                ['52200', 'EXPENSES:Event, Ads & Equipment:Events - Meeting/Workshop/Training/etc', 'Expenses', 'Meals and entertainment'],
                ['52300', 'EXPENSES:Event, Ads & Equipment:Publications/Reports', 'Expenses', 'Advertising/Promotional'],
                ['52400', 'EXPENSES:Event, Ads & Equipment:Subscription Cost', 'Expenses', 'Dues and Subscriptions'],
                ['52500', 'EXPENSES:Event, Ads & Equipment:Equipment', 'Expenses', 'Equipment rental'],
                ['52600', 'EXPENSES:Event, Ads & Equipment:Other Direct Cost', 'Expenses', 'Meals and entertainment'],
                ['52700', 'EXPENSES:Event, Ads & Equipment:Operational costs', 'Expenses', 'Office/General Administrative Expenses'],
                ['53000', 'EXPENSES:Travel and Transportation', 'Expenses', 'Travel expenses - general and admin expenses'],
                ['53100', 'EXPENSES:Travel and Transportation:Hotel/Accomodation', 'Expenses', 'Travel expenses - general and admin expenses'],
                ['53200', 'EXPENSES:Travel and Transportation:DSA - Daily Subsistence Allowance', 'Expenses', 'Travel expenses - general and admin expenses'],
                ['53300', 'EXPENSES:Travel and Transportation:Local Travel', 'Expenses', 'Travel expenses - general and admin expenses'],
                ['53400', 'EXPENSES:Travel and Transportation:Domestic Travel', 'Expenses', 'Travel expenses - general and admin expenses'],
                ['53500', 'EXPENSES:Travel and Transportation:International Travel', 'Expenses', 'Travel expenses - general and admin expenses'],
                ['53600', 'EXPENSES:Travel and Transportation:Travel Cost', 'Expenses', 'Travel expenses - general and admin expenses'],
                ['54000', 'EXPENSES:Miscellaneous/Administrative Costs', 'Expenses', 'Other Miscellaneous Service Cost'],
                ['54100', 'EXPENSES:Miscellaneous/Administrative Costs:Consumption', 'Expenses', 'Other Miscellaneous Service Cost'],
                ['54200', 'EXPENSES:Miscellaneous/Administrative Costs:External Audit', 'Expenses', 'Finance costs'],
                ['55000', 'EXPENSES:Operational Cost', 'Expenses', 'Office/General Administrative Expenses'],
                ['55100', 'EXPENSES:Operational Cost:Office Rent', 'Expenses', 'Rent or Lease of Buildings'],
                ['55200', 'EXPENSES:Operational Cost:Utilities Cost', 'Expenses', 'Utilities'],
                ['55300', 'EXPENSES:Operational Cost:Office Supplies', 'Expenses', 'Supplies and materials'],
                ['55400', 'EXPENSES:Operational Cost:Office Communication/Internet', 'Expenses', 'Office/General Administrative Expenses'],
                ['55500', 'EXPENSES:Operational Cost:Financial Service', 'Expenses', 'Bank charges'],
                ['55600', 'EXPENSES:Operational Cost:Office Maintenance', 'Expenses', 'Repair and maintenance'],
                ['55700', 'EXPENSES:Operational Cost:Dailly Needs/Kitchen Supplies', 'Expenses', 'Supplies and materials'],
                ['55800', 'EXPENSES:Operational Cost:Managemen Fee/Overhead Cost', 'Expenses', 'Office/General Administrative Expenses'],
                ['55900', 'EXPENSES:Operational Cost:Courier & Postage', 'Expenses', 'Shipping and delivery expense'],
                ['55910', 'EXPENSES:Operational Cost:Donations/Contributions', 'Expenses', 'Charitable Contributions'],
                ['55920', 'EXPENSES:Operational Cost:Transportation Cost', 'Expenses', 'Travel expenses - general and admin expenses'],
                ['55930', 'EXPENSES:Operational Cost:Bad Debt Expense', 'Expenses', 'Bad debts'],
                ['61000', 'OTHER INCOME', 'Other Income', 'Other Miscellaneous Income'],
                ['61100', 'OTHER INCOME:Bank Interest', 'Other Income', 'Interest earned'],
                ['61200', 'OTHER INCOME:Exchange Rate Gain', 'Other Income', 'Other operating income'],
                ['71000', 'Other Expense', 'Other Expense', 'Other Miscellaneous Expense'],
                ['71100', 'Other Expense:Exchange Loss', 'Other Expense', 'Other Miscellaneous Expense'],
                ['71200', 'Other Expense:Bank Charge & Tax Giro Bank', 'Other Expense', 'Other Miscellaneous Expense'],
                ['71300', 'Other Expense:Depreciation', 'Other Expense', 'Depreciation'],
                ['71400', 'Other Expense:Withholding Tax', 'Other Expense', 'Other Miscellaneous Expense'],
                ['71500', 'Other Expense:BPJS Ketenagakerjaan', 'Other Expense', 'Other Miscellaneous Expense'],
                ['71600', 'Other Expense:BPJS Kesehatan', 'Other Expense', 'Other Miscellaneous Expense'],
            ];
            $sheet->fromArray($coaTemplateRows, null, 'A2');
        } elseif ($isBankTemplate) {
            $bankTemplateRows = [
                ['Bank BNI', '460924700', 'BNI TET', 'TET', '10216', 'IDR', 'BNI-IDJA', 0, now()->startOfYear()->toDateString(), 'KT', now()->year, 'Active', 'Rekening fisik BNI 460924700 untuk Project TET (COA 10216).'],
                ['Bank BNI', '460924700', 'BNI Sekretariat 3', 'Sekretariat 3', '10220', 'IDR', 'BNI-IDJA', 0, now()->startOfYear()->toDateString(), 'KT', now()->year, 'Active', 'Rekening fisik BNI 460924700 untuk Project Sekretariat 3 (COA 10220).'],
                ['Bank BNI', '460924700', 'BNI Tifa Foundation', 'Tifa Foundation', '10227', 'IDR', 'BNI-IDJA', 0, now()->startOfYear()->toDateString(), 'KT', now()->year, 'Active', 'Rekening fisik BNI 460924700 untuk Project Tifa Foundation (COA 10227).'],
                ['Bank BNI', '628472846', 'BNI Norad Samdhana', 'Samdhana Permata', '10214', 'IDR', 'BNI-IDJA', 0, now()->startOfYear()->toDateString(), 'KT', now()->year, 'Active', 'Rekening fisik BNI 628472846 untuk Project Permata 2021-2025 (COA 10214).'],
                ['Bank BNI', '628472846', 'BNI RUU MA', 'RUU MA', '10228', 'IDR', 'BNI-IDJA', 0, now()->startOfYear()->toDateString(), 'KT', now()->year, 'Active', 'Rekening fisik BNI 628472846 untuk Project RUU MA 2026 (COA 10228).'],
            ];
            $sheet->fromArray($bankTemplateRows, null, 'A2');
        }
        $lastColumn = $sheet->getHighestColumn();
        $headerStyle = $sheet->getStyle("A1:{$lastColumn}1");
        $headerStyle->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $headerStyle->getFill()->setFillType('solid')->getStartColor()->setARGB('FF1F4E78');
        $headerStyle->getAlignment()->setHorizontal('center');
        $headerStyle->getAlignment()->setVertical('center');
        $headerStyle->getAlignment()->setWrapText(true);
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}1");
        $columnCount = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($lastColumn);
        for ($column = 1; $column <= $columnCount; $column++) {
            $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column);
            $sheet->getColumnDimension($letter)->setAutoSize(true);
        }
        if ($isBankTemplate) {
            $sheet->getColumnDimension($lastColumn)->setWidth(72);
            $sheet->getStyle("{$lastColumn}1")->getAlignment()->setWrapText(true);
        }

        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('Instructions');
        $instructionRows = [
            ['Template', $entityName],
            ['How to use', 'Enter data in the Input Data sheet. Do not change the column order. Add one row for each new record.'],
            ['Format import', 'Excel (.xlsx/.xls) atau CSV UTF-8. Kolom tampilan yang mudah dibaca dan nama field API sama-sama diterima saat import.'],
            ['Relasi', 'Kolom bertanda (ID) diisi dengan ID data terkait.'],
            ['Contoh data', ($isCoaTemplate || $isBankTemplate) ? 'Baris berwarna hanyalah contoh/dummy. Hapus seluruh baris contoh sebelum mengisi dan mengimpor data asli.' : 'Tidak ada baris contoh.'],
            ['', ''],
            ['Kolom pada template', 'Field import'],
        ];
        foreach ($columns as $column) {
            $instructionRows[] = [$headerOverrides[$column] ?? $this->displayHeader($column), $column];
        }
        $instructions->fromArray($instructionRows, null, 'A1');
        $instructions->getStyle('A1:A7')->getFont()->setBold(true);
        $instructions->getStyle('A7:B7')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $instructions->getStyle('A7:B7')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF1F4E78');
        $instructions->getColumnDimension('A')->setWidth(24);
        $instructions->getColumnDimension('B')->setWidth(110);
        $instructions->getStyle('B1:B3')->getAlignment()->setWrapText(true);

        $writer = new Xlsx($spreadsheet);
        $temp = tempnam(sys_get_temp_dir(), 'kt-template-');
        $writer->save($temp);
        return response()->download($temp, strtolower($entityName).'-template.xlsx')->deleteFileAfterSend(true);
    }

    public function templateHeaders(array $columns, array $headerOverrides = []): array
    {
        return array_map(fn (string $column): string => $headerOverrides[$column] ?? $this->displayHeader($column), $columns);
    }

    private function displayHeader(string $column): string
    {
        if ($column === 'is_active') return 'Active';
        if (str_ends_with($column, '_id')) {
            return ucwords(str_replace('_', ' ', substr($column, 0, -3))).' (ID)';
        }
        return ucwords(str_replace('_', ' ', $column));
    }

    public function export(string $entityName, $collection, string $format = 'csv')
    {
        $format = strtolower($format) === 'google_sheets' ? 'csv' : $format;
        $filename = strtolower($entityName) . '_' . date('Ymd_His');

        if ($format === 'pdf') {
            return $this->exportPdf($entityName, $collection, $filename);
        }

        if (in_array($format, ['xlsx', 'excel'], true)) {
            return $this->exportXlsx($entityName, $collection, $filename);
        }

        // Default CSV Streaming
        return $this->exportCsv($entityName, $collection, $filename);
    }

    private function exportCsv(string $entityName, $collection, string $filename): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($collection) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel compatibility

            if ($collection->isNotEmpty()) {
                $first = $collection->first()->toArray();
                $flatKeys = array_keys($this->flattenArray($first));
                fputcsv($handle, array_map(fn (string $key): string => $this->displayHeader($key), $flatKeys));

                foreach ($collection as $item) {
                    $row = $this->flattenArray($item->toArray());
                    fputcsv($handle, array_values($row));
                }
            } else {
                fputcsv($handle, ['No data available']);
            }

            fclose($handle);
        }, 200, $headers);
    }

    private function exportPdf(string $entityName, $collection, string $filename)
    {
        $pdf = Pdf::loadView('exports.master.template', [
            'entityName' => $entityName,
            'collection' => $collection,
            'timestamp' => now()->translatedFormat('d F Y H:i:s'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download("{$filename}.pdf");
    }

    private function exportXlsx(string $entityName, $collection, string $filename)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr(preg_replace('/[^A-Za-z0-9 ]/', '', $entityName), 0, 31) ?: 'Report');
        $rows = [];
        if ($collection->isNotEmpty()) {
            $first = $this->flattenArray($collection->first()->toArray());
            $flatKeys = array_keys($first);
            $rows[] = array_map(fn (string $key): string => $this->displayHeader($key), $flatKeys);
            foreach ($collection as $item) $rows[] = array_values($this->flattenArray($item->toArray()));
        } else {
            $rows[] = ['No data available'];
        }
        $sheet->fromArray($rows, null, 'A1');
        $lastColumn = $sheet->getHighestColumn();
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
        $sheet->freezePane('A2');
        foreach (range(1, $sheet->getHighestColumn() ? \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($lastColumn) : 1) as $column) {
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
        }
        $writer = new Xlsx($spreadsheet);
        $temp = tempnam(sys_get_temp_dir(), 'kt-export-');
        $writer->save($temp);
        return response()->download($temp, "{$filename}.xlsx")->deleteFileAfterSend(true);
    }

    public function flattenArray(array $array, string $prefix = ''): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            if (in_array($key, ['created_by', 'updated_by', 'deleted_by', 'deleted_at'])) {
                continue;
            }
            $newKey = $prefix === '' ? $key : "{$prefix}_{$key}";
            if (is_array($value)) {
                if (isset($value['name']) || isset($value['code']) || isset($value['title'])) {
                    $result[$newKey] = $value['name'] ?? $value['code'] ?? $value['title'];
                } else {
                    $result[$newKey] = json_encode($value);
                }
            } else {
                $result[$newKey] = $value;
            }
        }
        return $result;
    }
}
