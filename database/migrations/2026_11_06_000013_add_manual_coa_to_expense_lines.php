<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('expense_request_lines', function (Blueprint $table) { $table->foreignId('gl_account_id')->nullable()->after('budget_line_id')->constrained('chart_of_accounts')->nullOnDelete(); }); }
    public function down(): void { Schema::table('expense_request_lines', function (Blueprint $table) { $table->dropConstrainedForeignId('gl_account_id'); }); }
};
