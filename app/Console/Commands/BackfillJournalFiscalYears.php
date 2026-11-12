<?php

namespace App\Console\Commands;

use App\Models\Accounting\Journal;
use App\Models\Master\FiscalYear;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillJournalFiscalYears extends Command
{
    protected $signature = 'journals:backfill-fiscal-years {--dry-run} {--apply}';
    protected $description = 'Report invalid years and fill only uniquely matching missing journal years; default dry-run';

    public function handle(): int
    {
        $apply = $this->option('apply') && !$this->option('dry-run');
        $counts = ['examined' => 0, 'eligible' => 0, 'updated' => 0, 'ambiguous' => 0, 'invalid' => 0];
        Journal::withoutGlobalScope('fiscal_year_context')->orderBy('id')->chunkById(250, function ($journals) use ($apply, &$counts) {
            foreach ($journals as $journal) {
                $counts['examined']++;
                $date = $journal->journal_date?->toDateString();
                $years = FiscalYear::whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->pluck('id');
                if ($journal->fiscal_year_id) {
                    if (!$years->contains($journal->fiscal_year_id)) {
                        $counts['invalid']++;
                        $this->warn("Journal {$journal->id}: stored year {$journal->fiscal_year_id} conflicts with {$date}; no overwrite.");
                    }
                    continue;
                }
                if ($years->count() !== 1) {
                    $counts['ambiguous']++;
                    $this->warn("Journal {$journal->id}: matching years ".json_encode($years));
                    continue;
                }
                $yearId = (int)$years->first();
                $counts['eligible']++;
                $this->line("Journal {$journal->id}: fiscal_year_id={$yearId}");
                if ($apply) {
                    $counts['updated'] += DB::table('journals')->where('id', $journal->id)->whereNull('deleted_at')->whereNull('fiscal_year_id')->whereDate('journal_date', $date)->update(['fiscal_year_id' => $yearId]);
                }
            }
        });
        $this->info(($apply ? 'APPLY ' : 'DRY RUN ').json_encode($counts));
        return ($counts['invalid'] || $counts['ambiguous']) ? self::FAILURE : self::SUCCESS;
    }
}
