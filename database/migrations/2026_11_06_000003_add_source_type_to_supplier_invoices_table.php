<?php

use IlluminateDatabaseMigrationsMigration;
use IlluminateDatabaseSchemaBlueprint;
use IlluminateSupportFacadesSchema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('supplier_invoices', 'source_type')) {
            Schema::table('supplier_invoices', function (Blueprint $table) {
                $table->string('source_type', 40)->nullable()->index()->after('match_status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('supplier_invoices', 'source_type')) {
            Schema::table('supplier_invoices', fn (Blueprint $table) => $table->dropColumn('source_type'));
        }
    }
};
