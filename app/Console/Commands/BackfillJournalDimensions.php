<?php

namespace App\Console\Commands;

use App\Models\Accounting\JournalLine;
use App\Services\Accounting\JournalDimensionResolver;
use Illuminate\Console\Command;

class BackfillJournalDimensions extends Command
{
    protected $signature = 'journals:backfill-dimensions {--dry-run : Report only (default)} {--apply : Fill unambiguous missing dimensions}';
    protected $description = 'Fill missing journal dimensions without changing amounts; report ambiguous records';

    public function handle(JournalDimensionResolver $resolver): int
    {
        $apply = $this->option('apply') && !$this->option('dry-run');
        $counts = ['examined' => 0, 'eligible' => 0, 'updated' => 0, 'ambiguous' => 0];
        JournalLine::query()->orderBy('id')->chunkById(250, function ($lines) use ($resolver, $apply, &$counts) {
            foreach ($lines as $line) {
                $counts['examined']++;
                try {
                    $resolved = $resolver->resolve($line->getAttributes(), 0, true);
                    $changes = [];
                    foreach (['relation_no', 'donor_id', 'grant_agreement_id', 'program_id', 'project_id'] as $field) {
                        if (empty($line->$field) && !empty($resolved[$field])) $changes[$field] = $resolved[$field];
                    }
                    if (!$changes) continue;
                    $counts['eligible']++;
                    $this->line("Line {$line->id}: ".json_encode($changes));
                    if ($apply) {
                        // Recheck under a lock so concurrent edits cannot be overwritten.
                        \Illuminate\Support\Facades\DB::transaction(function () use ($line, $changes, &$counts) {
                            $current = JournalLine::query()->lockForUpdate()->findOrFail($line->id);
                            if ($current->getAttributes() !== $line->getAttributes()) {
                                throw new \RuntimeException('Concurrent change; retry this record.');
                            }
                            $current->update($changes);
                            $counts['updated']++;
                        });
                    }
                } catch (\Throwable $error) {
                    $counts['ambiguous']++;
                    $this->warn("Line {$line->id}: {$error->getMessage()}");
                }
            }
        });
        $this->info(($apply ? 'APPLY ' : 'DRY RUN ').json_encode($counts));
        return $counts['ambiguous'] ? self::FAILURE : self::SUCCESS;
    }
}
