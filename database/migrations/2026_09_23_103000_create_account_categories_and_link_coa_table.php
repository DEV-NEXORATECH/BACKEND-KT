<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('account_categories', function (Blueprint $table) {
            $table->id(); $table->string('code', 50)->unique(); $table->string('name', 150);
            $table->enum('account_type', ['asset','liability','equity','revenue','expense']);
            $table->text('description')->nullable(); $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable(); $table->unsignedBigInteger('updated_by')->nullable(); $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps(); $table->softDeletes();
        });
        Schema::table('chart_of_accounts', function (Blueprint $table) { $table->foreignId('account_category_id')->nullable()->after('parent_id')->constrained('account_categories')->nullOnDelete(); });
    }
    public function down(): void { Schema::table('chart_of_accounts', fn (Blueprint $table) => $table->dropConstrainedForeignId('account_category_id')); Schema::dropIfExists('account_categories'); }
};
