<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->string('supplier_waybill_number', 100)->nullable()->after('receipt_date');
            $table->string('receiving_location', 200)->nullable()->after('supplier_waybill_number');
            $table->string('donor_name', 200)->nullable()->after('receiving_location');
            $table->boolean('acceptable_for_payment')->nullable()->after('donor_name');
            $table->text('remarks')->nullable()->after('acceptable_for_payment');
            $table->string('receipt_by_name', 120)->nullable()->after('remarks');
            $table->string('receipt_by_position', 120)->nullable()->after('receipt_by_name');
            $table->string('requester_name', 120)->nullable()->after('receipt_by_position');
            $table->string('requester_position', 120)->nullable()->after('requester_name');
        });
        Schema::table('goods_receipt_lines', function (Blueprint $table) {
            $table->string('quality_check', 50)->nullable()->after('received_quantity');
            $table->text('comment')->nullable()->after('quality_check');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->dropColumn(['supplier_waybill_number', 'receiving_location', 'donor_name', 'acceptable_for_payment', 'remarks', 'receipt_by_name', 'receipt_by_position', 'requester_name', 'requester_position']);
        });
        Schema::table('goods_receipt_lines', function (Blueprint $table) {
            $table->dropColumn(['quality_check', 'comment']);
        });
    }
};
