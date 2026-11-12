<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Deliberately no automatic data rewrite. Review missing/conflicting
        // historical dimensions with journals:backfill-dimensions --dry-run,
        // then run --apply. Already-applied migrations require no rollback.
    }

    public function down(): void
    {
        // Never remove historical dimensions.
    }
};
