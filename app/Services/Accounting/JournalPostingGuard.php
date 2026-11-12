<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Journal;
use App\Models\User;
use App\Services\Rbac\DataScopeService;
use Illuminate\Validation\ValidationException;

class JournalPostingGuard
{
    public function assertReady(Journal $journal, User $user): int
    {
        abort_unless($user->hasPermission('accounting.journal.post'), 403, 'Tidak memiliki hak posting journal.');
        $query = Journal::withoutGlobalScope('fiscal_year_context')->whereKey($journal->id);
        app(DataScopeService::class)->applyScope($query, $user, 'created_by', null, null, ['accounting.journal.post']);
        abort_unless($query->exists(), 403, 'Tidak boleh mengubah journal ini.');
        if ($journal->status !== 'reviewed') throw ValidationException::withMessages(['status' => 'Journal harus reviewed sebelum approval/posting.']);
        $year = app(JournalFiscalYearService::class)->resolve($journal->journal_date->toDateString(), $journal->fiscal_year_id);
        $lines = $journal->lines()->get();
        if ($lines->count() < 2 || round((float)$lines->sum('debit'), 2) !== round((float)$lines->sum('credit'), 2)) {
            throw ValidationException::withMessages(['lines' => 'Journal harus memiliki minimal dua baris dan debit/kredit seimbang.']);
        }
        foreach ($lines as $index => $line) {
            if (((float)$line->debit > 0) === ((float)$line->credit > 0) || (float)$line->debit < 0 || (float)$line->credit < 0) {
                throw ValidationException::withMessages(["lines.{$index}" => 'Nilai debit/kredit journal tidak valid.']);
            }
            app(JournalDimensionResolver::class)->resolve($line->getAttributes(), $index, true);
        }
        return $year->id;
    }
}
