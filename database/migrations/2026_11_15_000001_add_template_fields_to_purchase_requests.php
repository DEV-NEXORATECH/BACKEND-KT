<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->date('required_date')->nullable();
            $table->string('country', 100)->nullable();
            $table->string('office', 100)->nullable();
            $table->string('requester_name', 150)->nullable();
            $table->string('requester_program', 150)->nullable();
            $table->string('requester_email', 150)->nullable();
            $table->string('requester_phone', 50)->nullable();
            $table->text('requester_address')->nullable();
            $table->string('destination', 255)->nullable();
            $table->string('donor_name', 255)->nullable();
            $table->string('delivery_method', 30)->nullable();
            $table->text('requirements')->nullable();
            $table->string('preferred_incoterm', 20)->nullable();
            $table->text('shipping_requirements')->nullable();
        });

        Schema::table('purchase_request_lines', function (Blueprint $table) {
            $table->string('account_code', 50)->nullable();
            $table->string('budget_code', 100)->nullable();
            $table->string('source_of_fund', 255)->nullable();
            $table->string('unit', 50)->nullable();
            $table->string('currency', 10)->default('IDR');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_request_lines', function (Blueprint $table) {
            $table->dropColumn(['account_code', 'budget_code', 'source_of_fund', 'unit', 'currency']);
        });
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropColumn([
                'required_date', 'country', 'office', 'requester_name', 'requester_program',
                'requester_email', 'requester_phone', 'requester_address', 'destination',
                'donor_name', 'delivery_method', 'requirements', 'preferred_incoterm',
                'shipping_requirements',
            ]);
        });
    }
};
