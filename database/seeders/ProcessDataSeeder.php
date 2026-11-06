<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Single entry point for a complete, connected demo process dataset.
 *
 * MasterDataSeeder remains responsible for reference/master records. This
 * seeder fills the operational chain after the master records exist.
 */
class ProcessDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            FeatureDataSeeder::class,
            UiDemoDataSeeder::class,
            CompleteFeatureDataSeeder::class,
            // Feature seeders create the operational Budget Lines. Repair
            // their explicit COA mappings before fiscal-year clones validate
            // those lines.
            ExpenseChartOfAccountsSeeder::class,
            ChartOfAccountHierarchySeeder::class,
            // Apply fiscal-year backfill and create the 2025/2027 counterparts
            // after every feature seeder has finished creating its records.
            FiscalYearDemoSeeder::class,
            // Fill secondary screens and detail tabs that are not reached by
            // the main donor-to-payment transaction chain.
            FullTrialFeatureSeeder::class,
            TrialDataCoverageSeeder::class,
        ]);

        // Keep seeded bank reconciliation statuses consistent with the
        // internal accounting chain. A bank line without a payment/journal
        // must not appear as reconciled in the reconciliation screen.
        if (DB::getSchemaBuilder()->hasTable('bank_transactions')) {
            DB::table('bank_transactions')
                ->whereIn('reference', ['STMT-UNMATCHED-20260401', 'STMT-MISMATCH-20260402'])
                ->update(['payment_id' => null, 'status' => 'unmatched', 'updated_at' => now()]);

            if (DB::getSchemaBuilder()->hasTable('payments')) {
                foreach (DB::table('bank_transactions')->whereNotNull('payment_id')->get(['id', 'payment_id']) as $bankTransaction) {
                    $journalId = DB::table('payments')->where('id', $bankTransaction->payment_id)->value('journal_id');
                    if (! $journalId) {
                        DB::table('bank_transactions')->where('id', $bankTransaction->id)->update([
                            'status' => 'matched',
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }
    }
}
