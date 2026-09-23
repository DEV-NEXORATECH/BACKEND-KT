<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tax_transactions', function (Blueprint $table) {
            $table->string('npwp', 30)->nullable()->after('e_bupot_reference')->comment('NPWP lawan transaksi');
            $table->string('e_faktur_number', 30)->nullable()->after('npwp')->comment('Nomor Seri Faktur Pajak (NSFP)');
            $table->string('e_bupot_number', 30)->nullable()->after('e_faktur_number')->comment('Nomor Bukti Potong');
        });
    }

    public function down(): void
    {
        Schema::table('tax_transactions', function (Blueprint $table) {
            $table->dropColumn(['npwp', 'e_faktur_number', 'e_bupot_number']);
        });
    }
};
