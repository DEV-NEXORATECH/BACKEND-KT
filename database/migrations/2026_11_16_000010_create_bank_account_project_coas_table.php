<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bank_account_project_coas')) {
            Schema::create('bank_account_project_coas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('bank_account_id')->constrained('bank_accounts')->cascadeOnDelete();
                $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
                $table->foreignId('gl_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
                $table->foreignId('fiscal_year_id')->nullable()->constrained('fiscal_years')->nullOnDelete();

                // Nomor Relasi (Relasi ke Project / Grant Agreement)
                $table->string('relation_no', 50)->nullable()->comment('Nomor relasi project/hibah, contoh: KT-PRJ-0001 atau KT-GRT-0001');

                // Field tambahan sesuai kebutuhan QuickBooks & kasus di foto
                $table->string('account_name', 150)->nullable()->comment('Nama akun di QuickBooks per project, misal: Bank Mandiri - Permata');
                $table->date('start_date')->nullable()->comment('Periode mulai project menggunakan rekening ini');
                $table->date('end_date')->nullable()->comment('Periode berakhir / habis masa project untuk rekening ini');
                $table->decimal('opening_balance', 18, 2)->default(0)->comment('Saldo awal COA bank project tersebut');
                $table->text('notes')->nullable()->comment('Catatan alokasi project');

                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->unsignedBigInteger('deleted_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['bank_account_id', 'project_id']);
                $table->index(['start_date', 'end_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_account_project_coas');
    }
};
