<?php

namespace Database\Seeders;

use App\Models\Master\Currency;
use App\Models\Master\ExchangeRate;
use App\Models\Master\FiscalYear;
use App\Models\Master\HolidayCalendar;
use Illuminate\Database\Seeder;

/**
 * Explicit demo/test fixture for FX rates and holidays.
 * Run manually; it is intentionally not included in DatabaseSeeder.
 */
class FxAndHolidayDemoSeeder extends Seeder
{
    public function run(): void
    {
        $idr = Currency::query()->where('code', 'IDR')->firstOrFail();
        $currencies = Currency::query()->whereIn('code', ['USD', 'EUR', 'GBP'])->get()->keyBy('code');

        $rates = ['USD' => 16250.000000, 'EUR' => 17800.000000, 'GBP' => 21200.000000];
        $years = range(2025, 2030);
        foreach ($years as $year) {
            $fiscalYear = FiscalYear::updateOrCreate(['year' => $year], [
                'name' => "Fiscal Year {$year}",
                'start_date' => "{$year}-01-01",
                'end_date' => "{$year}-12-31",
                'status' => 'open',
                'is_active' => $year === 2026,
            ]);
            $date = $year === 2027 ? "{$year}-01-04" : "{$year}-01-02";
            foreach ($rates as $code => $rate) {
                $from = $currencies->get($code);
                if (! $from) continue;
                ExchangeRate::updateOrCreate([
                    'date' => $date,
                    'from_currency_id' => $from->id,
                    'to_currency_id' => $idr->id,
                ], [
                    'rate' => $rate,
                    'rate_type' => 'spot',
                    'source' => 'BI - Bank Indonesia (demo fixture)',
                    'reference' => "SEED-FX-{$year}",
                    'is_active' => true,
                ]);
            }
        }

        $holidaysByYear = [
            // Official SKB 3 Menteri 2026 dates.
            2026 => [
                ['01-01', "New Year's Day", 'national_holiday'],
                ['01-16', 'Isra Mikraj Nabi Muhammad SAW', 'national_holiday'],
                ['02-16', 'Cuti Bersama Tahun Baru Imlek', 'cuti_bersama'],
                ['02-17', 'Tahun Baru Imlek', 'national_holiday'],
                ['03-18', 'Cuti Bersama Nyepi', 'cuti_bersama'],
                ['03-19', 'Hari Suci Nyepi', 'national_holiday'],
                ['03-20', 'Cuti Bersama Idul Fitri', 'cuti_bersama'],
                ['03-21', 'Idul Fitri', 'national_holiday'],
                ['03-22', 'Idul Fitri', 'national_holiday'],
                ['03-23', 'Cuti Bersama Idul Fitri', 'cuti_bersama'],
                ['03-24', 'Cuti Bersama Idul Fitri', 'cuti_bersama'],
                ['04-03', 'Wafat Yesus Kristus', 'national_holiday'],
                ['04-05', 'Kebangkitan Yesus Kristus (Paskah)', 'national_holiday'],
                ['05-01', 'Hari Buruh Internasional', 'national_holiday'],
                ['05-14', 'Kenaikan Yesus Kristus', 'national_holiday'],
                ['05-15', 'Cuti Bersama Kenaikan Yesus Kristus', 'cuti_bersama'],
                ['05-27', 'Idul Adha', 'national_holiday'],
                ['05-28', 'Cuti Bersama Idul Adha', 'cuti_bersama'],
                ['05-31', 'Hari Raya Waisak', 'national_holiday'],
                ['06-01', 'Hari Lahir Pancasila', 'national_holiday'],
                ['06-16', 'Tahun Baru Islam', 'national_holiday'],
                ['08-17', 'Hari Kemerdekaan Republik Indonesia', 'national_holiday'],
                ['08-25', 'Maulid Nabi Muhammad SAW', 'national_holiday'],
                ['12-24', 'Cuti Bersama Natal', 'cuti_bersama'],
                ['12-25', 'Hari Raya Natal', 'national_holiday'],
            ],
        ];

        foreach (FiscalYear::query()->whereIn('year', $years)->get() as $fiscalYear) {
            $holidays = $holidaysByYear[$fiscalYear->year] ?? [
                ['01-01', "New Year's Day", 'national_holiday'],
                ['05-01', 'Hari Buruh Internasional', 'national_holiday'],
                ['06-01', 'Hari Lahir Pancasila', 'national_holiday'],
                ['08-17', 'Hari Kemerdekaan Republik Indonesia', 'national_holiday'],
                ['12-25', 'Hari Raya Natal', 'national_holiday'],
            ];

            foreach ($holidays as [$monthDay, $name, $type]) {
                HolidayCalendar::updateOrCreate(
                    ['date' => "{$fiscalYear->year}-{$monthDay}"],
                    ['name' => $name, 'type' => $type, 'is_active' => true],
                );
            }
        }
    }
}
