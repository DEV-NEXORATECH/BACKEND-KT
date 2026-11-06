<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { if (!Schema::hasColumn('payments', 'attachments')) Schema::table('payments', fn (Blueprint $table) => $table->json('attachments')->nullable()->after('reference')); }
    public function down(): void { if (Schema::hasColumn('payments', 'attachments')) Schema::table('payments', fn (Blueprint $table) => $table->dropColumn('attachments')); }
};
