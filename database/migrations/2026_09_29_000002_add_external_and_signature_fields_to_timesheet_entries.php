<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('timesheet_entries', function (Blueprint $table) {
            $table->string('vendor_name', 180)->nullable()->after('worker_type');
            $table->string('contract_reference', 120)->nullable()->after('vendor_name');
            $table->string('invoice_reference', 120)->nullable()->after('contract_reference');
            $table->text('prepared_signature')->nullable()->after('decision_notes');
            $table->timestamp('prepared_signed_at')->nullable()->after('prepared_signature');
            $table->text('approved_signature')->nullable()->after('prepared_signed_at');
            $table->timestamp('approved_signed_at')->nullable()->after('approved_signature');
        });
    }

    public function down(): void
    {
        Schema::table('timesheet_entries', function (Blueprint $table) {
            $table->dropColumn(['vendor_name', 'contract_reference', 'invoice_reference', 'prepared_signature', 'prepared_signed_at', 'approved_signature', 'approved_signed_at']);
        });
    }
};
