<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('supplier_contract_notifications', function (Blueprint $table) {
            $table->date('completion_date')->nullable()->after('notification_date');
            $table->string('supplier_reference_number', 100)->nullable()->after('completion_date');
            $table->string('receiving_location')->nullable()->after('supplier_reference_number');
            $table->string('donor_name')->nullable()->after('receiving_location');
            $table->text('remarks')->nullable()->after('notes');
            $table->boolean('acceptable_for_payment')->default(true)->after('remarks');
            $table->string('receipt_by_name')->nullable()->after('acceptable_for_payment');
            $table->string('receipt_by_position')->nullable()->after('receipt_by_name');
            $table->string('requester_name')->nullable()->after('receipt_by_position');
            $table->string('requester_position')->nullable()->after('requester_name');
            $table->json('service_lines')->nullable()->after('requester_position');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_contract_notifications', function (Blueprint $table) {
            $table->dropColumn(['completion_date', 'supplier_reference_number', 'receiving_location', 'donor_name', 'remarks', 'acceptable_for_payment', 'receipt_by_name', 'receipt_by_position', 'requester_name', 'requester_position', 'service_lines']);
        });
    }
};
