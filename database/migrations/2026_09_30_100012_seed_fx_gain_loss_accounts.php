<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void {
        foreach ([
            ['code' => 'FX-LOSS', 'name' => 'Foreign Exchange Loss', 'account_type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => 'FX-GAIN', 'name' => 'Foreign Exchange Gain', 'account_type' => 'revenue', 'normal_balance' => 'credit'],
        ] as $account) {
            DB::table('chart_of_accounts')->updateOrInsert(['code' => $account['code']], [...$account, 'level' => 1, 'is_header' => false, 'is_active' => true, 'updated_at' => now(), 'created_at' => now()]);
        }
    }
    public function down(): void { DB::table('chart_of_accounts')->whereIn('code', ['FX-LOSS','FX-GAIN'])->delete(); }
};
